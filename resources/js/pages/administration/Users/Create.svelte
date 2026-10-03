<script module lang="ts">
    import { create as createUser, index as usersIndex } from '@/routes/users';

    export const layout = {
        breadcrumbs: [
            {
                title: 'New user',
                href: createUser(),
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

    let { roles }: { roles: { name: string }[] } = $props();
</script>

<AppHead title="New user" />

<h1 class="sr-only">New user</h1>

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="New user"
        description="Create an account and assign at least one role"
    />

    <Form {...UserController.store.form()} options={{ preserveScroll: true }}>
        {#snippet children({ errors, processing })}
            <div class="grid gap-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            autocomplete="off"
                            placeholder="Full name"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            required
                            autocomplete="off"
                            placeholder="name@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">Password</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
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
                            required
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
                    error={errors.roles ?? ''}
                />

                <div class="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>Create user</Button>
                    <Button variant="outline" asChild>
                        {#snippet children(props)}
                            <Link href={usersIndex()} class={props.class}>Cancel</Link>
                        {/snippet}
                    </Button>
                </div>
            </div>
        {/snippet}
    </Form>
</div>
