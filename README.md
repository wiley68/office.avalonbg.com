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
it creates no categories. New group initialization belongs to the group CRUD
phase. Seeded categories count as group data and restrict group deletion.

## Group foundation fixtures (IMP-01)

Use `User::factory()->officeUser($group)` (or omit `$group` to create one) for an
office user. The default `UserFactory` still permits groupless profiler/admin
fixtures. Create module fixtures with `Project::factory()->for($creator)` and
the analogous document, access, calendar and profit factories. A supplied creator
must have a group; defaults create a grouped office user. Use `recycle($group)`
to share one group throughout generated relationships. Tasks and email links
default to their project's creator; attachment documents use the link's group.
Explicit related fixtures must use the same group. Group keys are generated on
Eloquent creation, never provided as factory attributes.

IMP-01 prepares schema and models only. Existing HTTP creation, validation,
authorization and Accesses encryption still require IMP-02 through IMP-05 before
the new schema is ready for application use. Do not deploy this phase alone or
reset a shared database without the owner's explicit approval.
