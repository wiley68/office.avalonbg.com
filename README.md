# office.avalonbg.com
Simple office ai

## Local database bootstrap

Before an explicitly approved local seed, set `BOOTSTRAP_PROFILER_NAME`,
`BOOTSTRAP_PROFILER_EMAIL` and `BOOTSTRAP_PROFILER_PASSWORD` in your untracked local
`.env`. Choose a unique password of at least 12 characters with uppercase and
lowercase letters, numbers and symbols. Enter it in an editor or secret manager,
not in a shell command or committed file. There is no default password and the
seeder never prints it. Missing or invalid settings stop seeding before writes.

Use uncached local configuration for bootstrap. Remove the bootstrap password
afterwards and do not cache bootstrap credentials. Supply the settings again
only for a later explicitly approved seed. Re-running the seeder preserves an
existing profiler's password and two-factor configuration; it refuses to promote
an existing account of another role. The initial profiler has no group and must
complete the existing two-factor setup. No mail is sent by the seeder.

The seed creates only the existing three roles and the configured profiler. It
does not create demo groups or module records. `ProfitTypeSeeder` creates the
seven default categories independently for each existing group; with no groups,
it creates no categories. Groups created through administration start empty.
Seeded categories count as group data and restrict group deletion.

## Group architecture

The three roles are `profiler`, `admin` and `user`. The profiler manages admins;
admins manage groups and user-role accounts. Each user-role account belongs to
exactly one group; profiler and admin accounts remain groupless. Office access
requires an active account, the required password change and two-factor setup.

Accesses, projects, tasks, documents, calendar events and Profit records are
shared within their owning group. Creator attribution survives edits and group
reassignment; reassignment ends the user's sessions and does not move records.
Nonempty groups and creators with attributed records cannot be deleted.
Accesses use an automatically generated group encryption key, wrapped by
Laravel's application key. Keep `APP_KEY` stable and protect it with database
backups. IMAP credentials remain personal; archived project mail is group-shared.

## Group test fixtures

Use `User::factory()->officeUser($group)` (or omit `$group` to create one) for an
office user. The default `UserFactory` still permits groupless profiler/admin
fixtures. Create module fixtures with `Project::factory()->for($creator)` and
the analogous document, access, calendar and profit factories. A supplied creator
must have a group; defaults create a grouped office user. Use `recycle($group)`
to share one group throughout generated relationships. Tasks and email links
default to their project's creator; attachment documents use the link's group.
Explicit related fixtures must use the same group. Group keys are generated on
Eloquent creation, never provided as factory attributes.

The consolidated migration chain supports fresh installations. An existing
installation needs a separately reviewed upgrade or reset plan, a backup and
the owner's explicit approval. Build frontend assets after source updates;
generated build and Wayfinder files are not committed.
Regular Pest tests require SQLite `:memory:`. MySQL acceptance runners are
explicitly opt-in and restricted to the dedicated acceptance schema.
