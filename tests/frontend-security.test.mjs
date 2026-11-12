import assert from 'node:assert/strict';
import { createCipheriv, createDecipheriv, randomBytes } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import { createRenderer, h, nextTick, reactive } from 'vue';

// Compile the real sources with installed tooling; the renderer needs no browser DOM.
const moduleUrl = (source) => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const compile = (source) => ts.transpileModule(source, {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext },
}).outputText;
const vueUrl = import.meta.resolve('vue');
const revocationUrl = moduleUrl(compile(await readFile('resources/js/lib/accessRevocation.ts', 'utf8')).replaceAll("from 'vue'", `from '${vueUrl}'`));
const revocation = await import(revocationUrl);
async function loadSource(path) {
    return import(moduleUrl(compile(await readFile(path, 'utf8'))
        .replaceAll("from 'vue'", `from '${vueUrl}'`)
        .replaceAll("from '@/lib/accessRevocation'", `from '${revocationUrl}'`)));
}
const { useApiTable } = await loadSource('resources/js/composables/useApiTable.ts');
const { useApiLoadMore } = await loadSource('resources/js/composables/useApiLoadMore.ts');
const attachmentHelper = await loadSource('resources/js/lib/openEmailAttachment.ts');
const renderer = createRenderer({
    createElement: (type) => ({ type, tagName: type.toUpperCase(), props: {}, children: [], addEventListener() {}, removeEventListener() {} }),
    createText: (text) => ({ text }), createComment: () => ({}),
    insert(node, parent, anchor) {
        if (node.parent) {
 node.parent.children.splice(node.parent.children.indexOf(node), 1);
}

        parent.children ??= [];
        const index = anchor ? parent.children.indexOf(anchor) : -1;
        parent.children.splice(index < 0 ? parent.children.length : index, 0, node);
        node.parent = parent;
    },
    remove(node) {
        const children = node.parent?.children;

        if (children) {
 children.splice(children.indexOf(node), 1);
}

        node.parent = null;
    },
    parentNode: (node) => node.parent, nextSibling: () => null,
    patchProp(node, key, _previous, value) {
 node.props[key] = value;
},
    setText(node, text) {
 node.text = text;
},
    setElementText(node, text) {
 node.text = text; node.children = [];
},
});
function mountSetup(setup) {
    let state;
    const app = renderer.createApp({ setup() { state = setup(); return () => null; } });
    app.mount({});
    return { get state() { return state; }, unmount: () => app.unmount() };
}
function windowFixture() {
    globalThis.window = Object.assign(new EventTarget(), { location: new URL('https://office.test/projects/1') });
}
const json = (data, status = 200) => new Response(JSON.stringify(data), { status, headers: { 'Content-Type': 'application/json' } });
const page = (data) => json({ data, total: 5, current_page: 1, per_page: 10 });
const deferred = () => { let resolve; const promise = new Promise((done) => { resolve = done; }); return { promise, resolve }; };
const flush = () => new Promise((done) => setImmediate(done));

for (const [name, factory] of [['table', useApiTable], ['load more', useApiLoadMore]]) {
    test(`${name}: authorization failure clears rows/count; network failures preserve valid data`, async () => {
        windowFixture();
        const mounted = mountSetup(() => factory({ endpoint: '/internal-api/projects', autoload: false }));
        try {
            globalThis.fetch = async () => page([{ id: 1, secret: 'authorized' }]);
            await mounted.state.fetch();
            assert.equal(mounted.state.rows.value.length, 1);
            globalThis.fetch = async () => json({ message: 'temporary' }, 503);
            await mounted.state.fetch();
            assert.equal(mounted.state.rows.value.length, 1);
            globalThis.fetch = async () => { throw new TypeError('network offline'); };
            await mounted.state.fetch();
            assert.equal(mounted.state.rows.value.length, 1);
            for (const status of [401, 403, 404, 419]) {
                globalThis.fetch = async () => page([{ secret: 'cached' }]);
                await mounted.state.fetch();
                globalThis.fetch = async () => json({}, status);
                await mounted.state.fetch();
                assert.deepEqual(mounted.state.rows.value, []);
                assert.equal(name === 'table' ? mounted.state.pagination.value.rowsNumber : mounted.state.total.value, 0);
            }
        } finally { mounted.unmount(); }
    });
    test(`${name}: late requests cannot resurrect revoked or newer rows`, async () => {
        windowFixture();
        const mounted = mountSetup(() => factory({ endpoint: '/internal-api/projects', autoload: false }));
        try {
            const pending = deferred();
            globalThis.fetch = () => pending.promise;
            const oldRequest = mounted.state.fetch();
            globalThis.fetch = async () => page([{ id: 2 }]);
            await mounted.state.fetch();
            pending.resolve(page([{ secret: 'old response' }]));
            await oldRequest;
            assert.equal(mounted.state.rows.value[0].id, 2);
            const body = deferred();
            globalThis.fetch = async () => ({ ok: true, status: 200, redirected: false, json: () => body.promise });
            const reading = mounted.state.fetch();
            await flush();
            revocation.clearSensitiveState();
            body.resolve({ data: [{ secret: 'late JSON' }], total: 1 });
            await reading;
            assert.deepEqual(mounted.state.rows.value, []);
        } finally { mounted.unmount(); }
    });
}

test('fetch boundary sends context only to same origin and blocks all late successes after revocation', async () => {
    windowFixture();
    let clears = 0;
    let sentHeaders;
    let response = json({ data: [] });
    const guarded = revocation.protectFetch(async (_input, init) => { sentHeaders = init?.headers; return response; }, () => 'old-context', () => { clears++; });
    await guarded('/internal-api/projects');
    assert.equal(sentHeaders.get('X-Office-Context'), 'old-context');
    await guarded('https://external.test/api');
    assert.equal(sentHeaders, undefined);
    response = json({}, 419);
    await assert.rejects(guarded('/projects'), { name: 'AbortError' });
    assert.equal(clears, 1);
    await assert.rejects(guarded('/projects'), { name: 'AbortError' });
    assert.equal(clears, 1);
});

test('fetch boundary handles redirects and session/authorization errors while preserving temporary errors', async () => {
    windowFixture();
    for (const status of [401, 403, 419, 302]) {
        let revoked = false;
        const guarded = revocation.protectFetch(async () => ({ status, redirected: status === 302 }), () => null, () => { revoked = true; });
        await assert.rejects(guarded('/internal-api/projects'), { name: 'AbortError' });
        assert.equal(revoked, true);
    }
    const guarded = revocation.protectFetch(async () => json({}, 503), () => null, () => assert.fail('must preserve network errors'));
    assert.equal((await guarded('/internal-api/projects')).status, 503);
});

const stubs = moduleUrl(`
import { reactive } from '${vueUrl}';
export const router = { delete() {}, cancelAll() {} };
export const useAppToast = () => ({ showError() {}, showMessage() {} });
export const useTranslations = () => ({ t: (key) => key });
export const useForm = (initial) => {
    let defaults = { ...initial };
    const form = reactive({ ...initial, cancelled: false, processing: false, errors: {},
        defaults(value) { defaults = { ...value }; },
        reset() { Object.assign(form, defaults); }, clearErrors() {},
        cancel() { form.cancelled = true; },
        post(url, options) { globalThis.__accessSubmit?.('post', url, form, options); },
        put(url, options) { globalThis.__accessSubmit?.('put', url, form, options); },
    });
    return form;
};
export const documentsApiIndex = () => ({ url: '/internal-api/documents' });
export const store = () => ({ url: '/accesses' });
export const update = (id) => ({ url: '/accesses/' + id });
`);
async function loadComponent(path, renderTemplate = false) {
    const { descriptor } = parse(await readFile(path, 'utf8'));
    const script = compileScript(descriptor, { id: path });
    let code = compile(script.content);
    code = code.replace(/import ([\s\S]*?) from ['"]([^'"]+)['"];?/g, (statement, names, from) => {
        if (from === 'vue') { return statement.replace(from, vueUrl); }
        if (from === '@/lib/accessRevocation') { return statement.replace(from, revocationUrl); }

        if (['@/composables/useAppToast', '@/composables/useTranslations', '@/composables/useDocumentsApiRoute', '@/routes/accesses', '@inertiajs/vue3'].includes(from)) {
            // Inertia rendering/navigation are not used by these component state tests.
            const needed = names.replace(/\bLink\s*,?\s*/g, '');
            return `import ${needed} from '${stubs}';`;
        }
        const declarations = names.startsWith('{')
            ? names.slice(1, -1).split(',').map((name) => name.trim().split(/\s+as\s+/).pop()).filter(Boolean)
            : [names];

        return declarations.map((name) => renderTemplate
            ? `const ${name} = (props, { slots }) => renderNode('${name === 'Button' ? 'button' : name === 'Input' ? 'input' : 'div'}', props, slots.default?.());`
            : `const ${name} = () => null;`).join('\n');
    });

    if (renderTemplate) {
 code = `import { h as renderNode } from '${vueUrl}';\n${code}`;
}

    const component = (await import(moduleUrl(code))).default;

    if (renderTemplate) {
        const template = compileTemplate({ id: path, filename: path, source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } });
        assert.deepEqual(template.errors, []);
        component.render = (await import(moduleUrl(template.code.replaceAll('from "vue"', `from '${vueUrl}'`)))).render;
    }

    return component;
}
function mountComponent(component, props, renderTemplate = false) {
    let state;
    const reactiveProps = reactive(props);
    const originalSetup = component.setup;
    const wrapped = { ...component, setup(props, context) {
 state = originalSetup(props, context);

 return renderTemplate ? state : () => null;
} };
    const app = renderer.createApp({ setup: () => () => h(wrapped, reactiveProps) });
    const root = {};
    app.mount(root);

    return { get state() {
 return state;
}, props: reactiveProps, root, unmount: () => app.unmount() };
}

test('EmailPanel clears bodies, attachments, dialogs and blocks delayed body JSON on revocation', async () => {
    windowFixture();
    globalThis.fetch = async () => json({ data: { configured: true, threads: [] } });
    const component = await loadComponent('resources/js/components/projects/EmailPanel.vue');
    const mounted = mountComponent(component, { projectId: 1, hasImapConfigured: true });
    try {
        await flush();
        mounted.state.threads.value = [{ id: 'thread', message_count: 1, messages: [] }];
        mounted.state.bodies.value = { 1: { text: 'cached plaintext', html: null } };
        mounted.state.attachmentsByLinkId.value = { 1: [{ part: '2', filename: 'secret.pdf' }] };
        mounted.state.requestDelete(1);
        const body = deferred();
        globalThis.fetch = async () => ({ ok: true, status: 200, redirected: false, json: () => body.promise });
        const reading = mounted.state.toggleBody({ id: 1, status: 'active', can_read: true });
        await flush();
        revocation.clearSensitiveState();
        body.resolve({ data: { text: 'late plaintext', html: null } });
        await reading;
        assert.deepEqual(mounted.state.threads.value, []);
        assert.deepEqual(mounted.state.bodies.value, {});
        assert.deepEqual(mounted.state.attachmentsByLinkId.value, {});
        assert.equal(mounted.state.showDeleteDialog.value, false);
        assert.equal(mounted.state.expandedLinkId.value, null);
        globalThis.fetch = async () => { throw new Error('foreign mailbox must not be fetched'); };
        await mounted.state.toggleBody({ id: 2, status: 'active', can_read: false });
        assert.equal(mounted.state.expandedLinkId.value, null);
    } finally { mounted.unmount(); }
});

test('EmailPanel preserves cache on a network error and clears it on a scoped 404', async () => {
    windowFixture();
    globalThis.fetch = async () => json({ data: { configured: true, threads: [] } });
    const mounted = mountComponent(await loadComponent('resources/js/components/projects/EmailPanel.vue'), { projectId: 1, hasImapConfigured: true });
    try {
        await flush();
        mounted.state.bodies.value = { 1: { text: 'authorized', html: null } };
        globalThis.fetch = async () => { throw new TypeError('offline'); };
        await mounted.state.fetchThreads();
        assert.equal(mounted.state.bodies.value[1].text, 'authorized');
        globalThis.fetch = async () => json({}, 404);
        await mounted.state.fetchThreads();
        assert.deepEqual(mounted.state.bodies.value, {});
    } finally { mounted.unmount(); }
});

test('open Access form resets plaintext, cancels submission and refuses a late transformation', async () => {
    windowFixture();
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    const emitted = [];
    const component = await loadComponent('resources/js/components/accesses/AccessFormModal.vue');
    const mounted = mountComponent(component, { open: true, encryptionContext: 'group-context', mode: 'create', 'onUpdate:open': (value) => emitted.push(value) });
    try {
        mounted.state.form.content = 'secret plaintext';
        mounted.state.form.name = 'obsolete form';
        const pending = deferred();
        globalThis.fetch = () => pending.promise;
        const transforming = mounted.state.transformData('encrypt');
        revocation.clearSensitiveState();
        pending.resolve(json({ content: 'late secret', is_encrypted: true }));
        await transforming;
        assert.equal(mounted.state.form.content, '');
        assert.equal(mounted.state.form.name, '');
        assert.equal(mounted.state.form.cancelled, true);
        assert.equal(emitted.at(-1), false);
    } finally { mounted.unmount(); }
});

const editorPath = 'resources/js/components/accesses/AccessFormModal.vue';
const editorContext = 'c'.repeat(64);
async function mountEditor(props = {}) {
    windowFixture();
    globalThis.document = { querySelector: () => ({ content: 'synthetic-csrf' }) };
    let mounted;
    mounted = mountComponent(await loadComponent(editorPath, true), {
        open: true, encryptionContext: editorContext, mode: 'create',
        'onUpdate:open': (open) => {
 mounted.props.open = open;
}, ...props,
    }, true);

    return mounted;
}
function editorNodes(node) {
    return [node, ...(node.children ?? []).flatMap(editorNodes)];
}
function editorButton(mounted, label) {
    const button = editorNodes(mounted.root).find((node) => node.type === 'button' && editorNodes(node).some((child) => child.text?.includes(label)));
    assert.ok(button, `Editor button ${label} exists`);

    return button;
}
const editorTextarea = (mounted) => editorNodes(mounted.root).find((node) => node.type === 'textarea');
function syntheticCipher() {
    const key = randomBytes(32);

    return {
        encrypt(content) {
            const iv = randomBytes(12);
            const cipher = createCipheriv('aes-256-gcm', key, iv);
            const encrypted = Buffer.concat([cipher.update(content, 'utf8'), cipher.final()]);

            return Buffer.concat([iv, cipher.getAuthTag(), encrypted]).toString('base64');
        },
        decrypt(content) {
            const raw = Buffer.from(content, 'base64');
            const cipher = createDecipheriv('aes-256-gcm', key, raw.subarray(0, 12));
            cipher.setAuthTag(raw.subarray(12, 28));

            return Buffer.concat([cipher.update(raw.subarray(28)), cipher.final()]).toString('utf8');
        },
    };
}
const transformed = (content, encrypted = true, context = editorContext) => json({ content, is_encrypted: encrypted, encryption_context: context });

test('actual Access editor supports C1 → C2 → C1 and repeated own/shared edits with exactly one encryption layer', async () => {
    const cipher = syntheticCipher();
    let requests = 0;
    let record;
    let editor = 'C1';
    let expected = 'Initial synthetic access';
    globalThis.fetch = async (_url, init) => {
        requests++;
        const body = JSON.parse(init.body);

        return transformed(body.action === 'encrypt' ? cipher.encrypt(body.content) : cipher.decrypt(body.content), body.action === 'encrypt');
    };
    globalThis.__accessSubmit = (method, _url, form, options) => {
        assert.equal(form.is_encrypted, true);
        assert.equal(cipher.decrypt(form.content), expected);
        assert.ok(method === 'post' || method === 'put');
        record = {
            ...record, id: 1, name: form.name, category: form.category, content: form.content,
            is_encrypted: form.is_encrypted, encryption_context: editorContext,
            version: String(requests).padStart(64, '0'), user_id: record?.user_id ?? editor, group_id: 'C',
        };
        options.onSuccess();
    };
    const mounted = await mountEditor();

    try {
        mounted.state.form.name = 'Synthetic fixture';
        mounted.state.form.content = expected;
        await nextTick();
        assert.equal(editorButton(mounted, 'accesses.actions.decrypt').props.disabled, true);
        await mounted.state.transformData('decrypt');
        assert.equal(requests, 0);
        await editorButton(mounted, 'accesses.actions.encrypt').props.onClick();
        await nextTick();
        assert.equal(editorButton(mounted, 'accesses.actions.encrypt').props.disabled, true);
        assert.equal(editorTextarea(mounted).props.readonly, true);
        await mounted.state.transformData('encrypt');
        assert.equal(requests, 1);
        mounted.state.submit();
        await nextTick();

        for (const [iteration, actor] of ['C2', 'C1', 'C2', 'C1'].entries()) {
            editor = actor;
            mounted.props.mode = 'edit';
            mounted.props.access = { ...record };
            mounted.props.open = true;
            await nextTick();
            assert.equal(mounted.state.form.content, record.content);
            await editorButton(mounted, 'accesses.actions.decrypt').props.onClick();
            await nextTick();
            assert.equal(mounted.state.form.content, expected);
            assert.equal(mounted.state.form.is_encrypted, false);
            assert.equal(editorButton(mounted, 'common.save').props.disabled, true);
            expected = `Edited by ${actor}, pass ${iteration} — проверка`;
            editorTextarea(mounted).props['onUpdate:modelValue'](expected);
            await nextTick();
            await editorButton(mounted, 'accesses.actions.encrypt').props.onClick();
            await nextTick();
            const beforeRepeat = requests;
            await mounted.state.transformData('encrypt');
            assert.equal(requests, beforeRepeat);
            mounted.state.submit();
            await nextTick();
            assert.equal(record.user_id, 'C1');
            assert.equal(record.group_id, 'C');

            mounted.props.access = { ...record };
            mounted.props.open = true;
            await nextTick();
            await editorButton(mounted, 'accesses.actions.decrypt').props.onClick();
            assert.equal(mounted.state.form.content, expected);
            mounted.props.open = false;
            await nextTick();
        }
    } finally {
 mounted.unmount(); delete globalThis.__accessSubmit;
}
});

test('Access editor blocks UI and handler saves and overlapping transforms while a request is pending', async () => {
    const mounted = await mountEditor();
    const pending = deferred();
    let requests = 0;
    let saves = 0;
    globalThis.fetch = () => {
 requests++;

 return pending.promise;
};
    globalThis.__accessSubmit = () => {
 saves++;
};

    try {
        mounted.state.form.content = 'Synthetic pending text';
        const request = mounted.state.transformData('encrypt');
        await nextTick();
        assert.equal(editorTextarea(mounted).props.disabled, true);
        assert.equal(editorButton(mounted, 'common.save').props.disabled, true);
        assert.equal(editorButton(mounted, 'accesses.actions.encrypting').props.disabled, true);
        await mounted.state.transformData('encrypt');
        await mounted.state.transformData('decrypt');
        mounted.state.submit();
        assert.equal(requests, 1);
        assert.equal(saves, 0);
        pending.resolve(transformed('Synthetic encrypted result'));
        await request;
        mounted.state.form.processing = true;
        await nextTick();
        assert.equal(editorButton(mounted, 'accesses.actions.decrypt').props.disabled, true);
        await mounted.state.transformData('decrypt');
        mounted.state.submit();
        assert.equal(requests, 1);
        assert.equal(saves, 0);
    } finally {
 mounted.unmount(); delete globalThis.__accessSubmit;
}
});

test('Access editor preserves edits and keeps a newer request pending when an older response completes', async () => {
    const mounted = await mountEditor();
    const oldResponse = deferred();
    const newResponse = deferred();
    const calls = [];
    globalThis.fetch = (_url, init) => {
 calls.push(init);

 return calls.length === 1 ? oldResponse.promise : newResponse.promise;
};

    try {
        mounted.state.form.content = 'Original synthetic content';
        const oldRequest = mounted.state.transformData('encrypt');
        mounted.state.form.content = 'New user edit';
        assert.equal(calls[0].signal.aborted, true);
        const newRequest = mounted.state.transformData('encrypt');
        oldResponse.resolve(transformed('Obsolete synthetic response'));
        await oldRequest;
        assert.equal(mounted.state.form.content, 'New user edit');
        assert.equal(mounted.state.form.is_encrypted, false);
        assert.equal(mounted.state.isTransforming.value, true);
        newResponse.resolve(transformed('Current synthetic response'));
        await newRequest;
        assert.equal(mounted.state.form.content, 'Current synthetic response');
        assert.equal(mounted.state.isTransforming.value, false);
    } finally {
 mounted.unmount();
}
});

test('closing the Access dialog invalidates its transform before the parent updates the open prop', async () => {
    const mounted = await mountEditor({ 'onUpdate:open': () => {} });
    const pending = deferred();
    let signal;
    globalThis.fetch = (_url, init) => {
        signal = init.signal;

        return pending.promise;
    };

    try {
        mounted.state.form.content = 'Synthetic pending text';
        const request = mounted.state.transformData('encrypt');
        mounted.state.updateOpen(false);
        assert.equal(mounted.props.open, true, 'The parent has not propagated closure yet');
        assert.equal(signal.aborted, true);
        pending.resolve(transformed('Discarded synthetic response'));
        await request;
        assert.equal(mounted.state.form.content, '');
        assert.equal(mounted.state.form.is_encrypted, false);
    } finally {
        mounted.unmount();
    }
});

for (const change of ['close-reopen', 'record', 'version', 'encryption-context', 'content-reverted', 'flag']) {
    test(`Access editor discards delayed transformations after ${change} changes`, async () => {
        const record = { id: 1, name: 'Synthetic access', category: null, content: 'Synthetic plaintext', is_encrypted: false, encryption_context: editorContext, version: '1'.repeat(64) };
        const mounted = await mountEditor({ mode: 'edit', access: record });
        const pending = deferred();
        let signal;
        globalThis.fetch = (_url, init) => {
 signal = init.signal;

 return pending.promise;
};

        try {
            const request = mounted.state.transformData('encrypt');

            if (change === 'close-reopen') {
                mounted.props.open = false;
                await nextTick();
                mounted.props.open = true;
            } else if (change === 'record') {
                mounted.props.access = { ...record, id: 2, content: 'Other synthetic record' };
            } else if (change === 'version') {
                mounted.props.access.version = '2'.repeat(64);
            } else if (change === 'encryption-context') {
                mounted.props.access.encryption_context = 'd'.repeat(64);
            } else if (change === 'content-reverted') {
                mounted.state.form.content = 'Intermediate edit';
                mounted.state.form.content = record.content;
            } else {
                mounted.state.form.is_encrypted = true;
            }

            await nextTick();
            const content = mounted.state.form.content;
            const encrypted = mounted.state.form.is_encrypted;
            pending.resolve(transformed('Obsolete transformed content'));
            await request;
            assert.equal(signal.aborted, true);
            assert.equal(mounted.state.form.content, content);
            assert.equal(mounted.state.form.is_encrypted, encrypted);
            assert.equal(mounted.state.isTransforming.value, false);
        } finally {
 mounted.unmount();
}
    });
}

test('Access editor requires deliberate plaintext removal and resets consent after edits or reopening', async () => {
    const record = { id: 1, name: 'Synthetic access', content: 'Synthetic ciphertext', is_encrypted: true, encryption_context: editorContext, version: '1'.repeat(64) };
    const mounted = await mountEditor({ mode: 'edit', access: record });
    let requests = 0;
    const saves = [];
    globalThis.fetch = async () => {
 requests++;

 return transformed('Decrypted synthetic text', false);
};
    globalThis.__accessSubmit = (_method, _url, form) => {
 saves.push({ ...form });
};

    try {
        await mounted.state.transformData('decrypt');
        await nextTick();
        mounted.state.submit();
        assert.equal(saves.length, 0);
        assert.equal(editorButton(mounted, 'common.save').props.disabled, true);
        const checkbox = editorNodes(mounted.root).find((node) => node.props?.id === 'access-remove-encryption');
        assert.ok(checkbox);
        checkbox.props['onUpdate:modelValue'](true);
        mounted.state.form.content = 'Explicitly edited plaintext';
        assert.equal(mounted.state.form.remove_encryption, false);
        mounted.state.submit();
        assert.equal(saves.length, 0);
        checkbox.props['onUpdate:modelValue'](true);
        mounted.state.submit();
        assert.equal(saves.length, 1);
        assert.equal(saves[0].content, 'Explicitly edited plaintext');
        assert.equal(saves[0].is_encrypted, false);
        assert.equal(saves[0].remove_encryption, true);
        assert.equal(requests, 1, 'Save must not silently encrypt');
        mounted.props.open = false;
        await nextTick();
        mounted.props.open = true;
        await nextTick();
        assert.equal(mounted.state.form.remove_encryption, false);
        assert.equal(mounted.state.form.is_encrypted, true);
    } finally {
 mounted.unmount(); delete globalThis.__accessSubmit;
}
});

for (const invalid of [json({ content: 'Wrong state', is_encrypted: false, encryption_context: editorContext }), transformed('Wrong context', true, 'd'.repeat(64)), json({ is_encrypted: true, encryption_context: editorContext }), json({ message: 'Rejected' }, 422)]) {
    test('Access editor preserves form content on an invalid or rejected transform response', async () => {
        const mounted = await mountEditor();
        globalThis.fetch = async () => invalid;

        try {
            mounted.state.form.content = 'Unchanged synthetic text';
            await mounted.state.transformData('encrypt');
            assert.equal(mounted.state.form.content, 'Unchanged synthetic text');
            assert.equal(mounted.state.form.is_encrypted, false);
            assert.equal(mounted.state.isTransforming.value, false);
        } finally {
 mounted.unmount();
}
    });
}

test('SVG preview helpers refuse active SVG even when the supplied MIME suggests PNG', () => {
    assert.equal(attachmentHelper.canOpenEmailAttachmentInline({ filename: 'hostile.SVG', mime_type: 'image/png' }), false);
    assert.equal(attachmentHelper.canOpenEmailAttachmentInline({ filename: 'image.png', mime_type: 'image/svg+xml' }), false);
    assert.equal(attachmentHelper.canOpenEmailAttachmentInline({ filename: 'image.png', mime_type: 'image/png' }), true);
});

test('DocumentPicker clears its list, closes the obsolete dialog and ignores delayed results', async () => {
    windowFixture();
    globalThis.fetch = async () => json({ data: [{ id: 1, original_name: 'secret.pdf', description: null }] });
    const emitted = [];
    const mounted = mountComponent(await loadComponent('resources/js/components/documents/DocumentPickerModal.vue'), { open: true, 'onUpdate:open': (value) => emitted.push(value) });
    try {
        await flush();
        assert.equal(mounted.state.documents.value.length, 1);
        const pending = deferred();
        globalThis.fetch = () => pending.promise;
        const reading = mounted.state.fetchDocuments();
        revocation.clearSensitiveState();
        pending.resolve(json({ data: [{ id: 2, original_name: 'late-secret.pdf' }] }));
        await reading;
        assert.deepEqual(mounted.state.documents.value, []);
        assert.equal(emitted.at(-1), false);
    } finally { mounted.unmount(); }
});

test('revocation subscriptions also allow server rendering without a window', () => {
    delete globalThis.window;
    const mounted = mountSetup(() => useApiTable({ endpoint: '/internal-api/projects', autoload: false }));
    assert.deepEqual(mounted.state.rows.value, []);
    mounted.unmount();
    windowFixture();
});

const bootstrapStubs = moduleUrl(`
const state = () => globalThis.__officeBootstrap;
export const router = {
    on(name, callback) { state().listeners[name] = callback; },
    cancelAll() { state().cancelled = true; },
    flushAll() { state().flushed = true; },
};
export const createInertiaApp = (options) => options.setup({
    el: { replaceChildren() { state().emptied = true; } }, App: {}, plugin: {},
    props: { initialPage: { props: { authorizationContext: state().context } } },
});
export const createApp = () => ({ use() { return this; }, mount() {}, unmount() { state().unmounted = true; } });
export const h = () => null;
export const resolvePageComponent = () => null;
export const syncThemeFromPageProps = () => {};
export const initializeTheme = () => {};
export const dashboard = () => ({ url: '/dashboard' });
`);
let bootstrapNumber = 0;
async function bootstrapApp(context) {
    windowFixture();
    const state = { context, listeners: {} };
    globalThis.__officeBootstrap = state;
    window.location.replace = (url) => { state.redirect = url; };
    window.addEventListener('office:access-revoked', () => { state.cleared = true; });
    globalThis.fetch = window.fetch = async () => json({});
    let code = compile(await readFile('resources/js/app.ts', 'utf8'));
    code = code.replace(/import ['"][^'"]+\.css['"];?/g, '')
        .replaceAll('import.meta.env', '({})')
        .replace(/from ['"]([^'"]+)['"]/g, (_statement, from) => `from '${from === '@/lib/accessRevocation' ? revocationUrl : bootstrapStubs}'`);
    await import(moduleUrl(`${code}\n// bootstrap instance ${++bootstrapNumber}`));
    return state;
}

test('app bootstrap preserves same-context navigation and rejects obsolete Inertia pages', async () => {
    const state = await bootstrapApp('current-context');
    const visit = { url: new URL('/projects', window.location.href), headers: {} };
    state.listeners.before({ detail: { visit } });
    assert.equal(visit.headers['X-Office-Context'], 'current-context');
    state.listeners.navigate({ detail: { page: { url: '/projects', props: { authorizationContext: 'current-context' } } } });
    assert.equal(state.unmounted, undefined);
    state.listeners.navigate({ detail: { page: { url: '/projects/old', props: { authorizationContext: 'obsolete-context' } } } });
    assert.equal(state.cleared, true);
    assert.equal(state.unmounted, true);
    assert.equal(state.emptied, true);
    assert.equal(state.cancelled, true);
    assert.equal(state.flushed, true);
    assert.equal(state.redirect, '/projects/old');
});

test('app bootstrap clears forms on an Inertia denial and reloads an authenticated entry point', async () => {
    const state = await bootstrapApp('current-context');
    let prevented = false;
    state.listeners.invalid({ detail: { response: { status: 419 } }, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(state.unmounted, true);
    assert.equal(state.cleared, true);
    assert.equal(state.redirect, '/dashboard');
});

test('legitimate login and logout retain the server-selected destination', async () => {
    const state = await bootstrapApp(null);
    state.listeners.navigate({ detail: { page: { url: '/projects/5', props: { authorizationContext: 'signed-in-context' } } } });
    assert.equal(state.redirect, '/projects/5');
    const signedIn = await bootstrapApp('signed-in-context');
    signedIn.listeners.navigate({ detail: { page: { url: '/', props: { authorizationContext: null } } } });
    assert.equal(signedIn.redirect, '/');
});
