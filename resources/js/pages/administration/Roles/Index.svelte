<script module lang="ts">
    import { index as rolesIndex } from '@/routes/roles';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Roles',
                href: rolesIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import RoleController from '@/actions/App/Http/Controllers/Administration/RoleController';
    import AppHead from '@/components/AppHead.svelte';
    import CheckboxGroup from '@/components/CheckboxGroup.svelte';
    import Heading from '@/components/Heading.svelte';
    import PermissionCheckbox from '@/components/PermissionCheckbox.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type RoleRow = {
        id: number;
        name: string;
        permissions: string[];
        users_count: number;
    };

    let {
        roles,
        permissionGroups,
        permissionLabels,
        defaultRoles,
    }: {
        roles: RoleRow[];
        permissionGroups: Record<string, string[]>;
        permissionLabels: Record<string, string>;
        defaultRoles: string[];
    } = $props();

    let editingId = $state<number | null>(null);

    const groupEntries = $derived(
        Object.entries(permissionGroups).map(([group, permissions]) => ({
            group,
            permissions: permissions.map((permission) => ({
                name: permission,
                label: permissionLabels[permission] ?? permission,
            })),
        })),
    );
</script>

<AppHead title="Roles" />

<h1 class="sr-only">Roles</h1>

<div class="flex flex-col gap-6 p-4">
    <div>
        <h2 class="text-xl font-semibold tracking-tight">Roles</h2>
        <p class="text-sm text-muted-foreground">
            Roles group the permissions that authorize financial actions. Use
            least privilege: grant a user only what their work requires.
        </p>
    </div>

    <div class="grid gap-4">
        {#each roles as role (role.id)}
            <div class="rounded-lg border border-border/70">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/70 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <h3 class="font-medium">{role.name}</h3>
                        {#if defaultRoles.includes(role.name)}
                            <Badge variant="secondary">Default</Badge>
                        {/if}
                        <Badge variant="outline">
                            {role.users_count}
                            {role.users_count === 1 ? 'user' : 'users'}
                        </Badge>
                    </div>
                    <Button
                        size="sm"
                        variant="outline"
                        onclick={() =>
                            (editingId = editingId === role.id ? null : role.id)}
                    >
                        {editingId === role.id ? 'Close' : 'Edit permissions'}
                    </Button>
                </div>

                <div class="flex flex-wrap gap-1 px-4 py-3">
                    {#each role.permissions as permission (permission)}
                        <Badge variant="secondary">
                            {permissionLabels[permission] ?? permission}
                        </Badge>
                    {:else}
                        <span class="text-sm text-muted-foreground">
                            No permissions assigned.
                        </span>
                    {/each}
                </div>

                {#if editingId === role.id}
                    <div class="border-t border-border/70 px-4 py-4">
                        <Form {...RoleController.update.form(role.id)}>
                            {#snippet children({ errors, processing })}
                                <div class="grid gap-6">
                                    <div class="grid gap-2 sm:max-w-sm">
                                        <Label for="name-{role.id}">Name</Label>
                                        <Input
                                            id="name-{role.id}"
                                            name="name"
                                            value={role.name}
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    {#each groupEntries as entry (entry.group)}
                                        <div class="grid gap-2">
                                            <p class="text-sm font-medium">
                                                {entry.group}
                                            </p>
                                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                {#each entry.permissions as permission (permission.name)}
                                                    <PermissionCheckbox
                                                        name="permissions[]"
                                                        label={permission.label}
                                                        value={permission.name}
                                                        checked={role.permissions.includes(
                                                            permission.name,
                                                        )}
                                                    />
                                                {/each}
                                            </div>
                                        </div>
                                    {/each}

                                    <p class="text-sm text-destructive">
                                        {errors.permissions ?? ''}
                                    </p>

                                    <div class="flex items-center gap-2">
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Save role
                                        </Button>
                                    </div>
                                </div>
                            {/snippet}
                        </Form>
                    </div>
                {/if}
            </div>
        {/each}
    </div>

    <div class="rounded-lg border border-border/70 p-4">
        <Heading variant="small" title="New role" description="" />
        <Form {...RoleController.store.form()} class="mt-4 grid gap-4">
            {#snippet children({ errors, processing })}
                <div class="grid gap-2 sm:max-w-sm">
                    <Label for="new-role-name">Name</Label>
                    <Input
                        id="new-role-name"
                        name="name"
                        required
                        placeholder="e.g. Auditor"
                    />
                    <InputError message={errors.name} />
                </div>

                {#each groupEntries as entry (entry.group)}
                    <CheckboxGroup
                        name="permissions"
                        legend={entry.group}
                        options={entry.permissions.map(
                            (permission) => permission.name,
                        )}
                    />
                {/each}

                <div>
                    <Button type="submit" size="sm" disabled={processing}>
                        Create role
                    </Button>
                </div>
            {/snippet}
        </Form>
    </div>
</div>
