/**
 * Чете SSE отговор от офис агента (Laravel AI JSON събития + meta + [DONE]).
 */
export async function consumeAgentSseStream(
    body: ReadableStream<Uint8Array>,
    onTextDelta: (delta: string) => void,
    onMeta: (conversationId: string | null) => void,
): Promise<void> {
    const reader = body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });

        const parts = buffer.split('\n\n');
        buffer = parts.pop() ?? '';

        for (const part of parts) {
            const lines = part
                .split('\n')
                .map((line) => line.trim())
                .filter((line) => line.length > 0);

            if (lines.length === 0) {
                continue;
            }

            let eventName: string | null = null;
            const dataLines: string[] = [];

            for (const line of lines) {
                if (line.startsWith('event:')) {
                    eventName = line.slice(6).trim();
                    continue;
                }

                if (line.startsWith('data:')) {
                    dataLines.push(line.slice(5).trim());
                }
            }

            if (dataLines.length === 0) {
                continue;
            }

            const payload = dataLines.join('\n').trim();

            if (payload === '[DONE]') {
                continue;
            }

            try {
                const data = JSON.parse(payload) as {
                    type?: string;
                    delta?: string;
                    conversation_id?: string | null;
                };

                if (data.type === 'text_delta' && typeof data.delta === 'string') {
                    onTextDelta(data.delta);
                }

                if (data.type === 'meta' || eventName === 'meta') {
                    onMeta(data.conversation_id ?? null);
                }
            } catch {
                // Игнориране на невалидни редове
            }
        }
    }
}
