<script module lang="ts">
    import { edit as editUser, index as usersIndex } from '@/routes/users';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Users',
                href: usersIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, Link } from '@inertiajs/svelte';
    import UserController from '@/actions/App/Http/Controllers/Administration/UserController';
    import AppHead from '@/components/AppHead.svelte';
    import CheckboxGroup from '@/components/CheckboxGroup.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type UserRecord = {
        id: number;
        name: string;
        email: string;
        roles: string[];
        created_at: string | null;
    };

    let { user, roles }: { user: UserRecord; roles: { name: string }[] } =
        $props();

    const title = $derived(`Edit ${user.name}`);
</script>

<AppHead title={title} />

<h1 class="sr-only">{title}</h1>

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title={title}
        description="Update the account details and the roles it holds"
    />

    <Form {...UserController.update.form(user.id)} options={{ preserveScroll: true }}>
        {#snippet children({ errors, processing })}
            <div class="grid gap-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            value={user.name}
                            required
                            autocomplete="off"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            value={user.email}
                            required
                            autocomplete="off"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">New password</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Leave blank to keep the current password"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">
                            Confirm password
                        </Label>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                        />
                        <InputError
                            message={errors.password_confirmation}
                        />
                    </div>
                </div>

                <CheckboxGroup
                    name="roles"
                    legend="Roles"
                    options={roles.map((role) => role.name)}
                    selected={user.roles}
                    error={errors.roles ?? ''}
                />

                <div class="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>Save</Button>
                    <Button variant="outline" asChild>
                        {#snippet children(props)}
                            <Link href={usersIndex()} class={props.class}>
                                Back to users
                            </Link>
                        {/snippet}
                    </Button>
                    {#if user.created_at}
                        <span class="text-sm text-muted-foreground">
                            Created {user.created_at}
                        </span>
                    {/if}
                </div>
            </div>
        {/snippet}
    </Form>
</div>
