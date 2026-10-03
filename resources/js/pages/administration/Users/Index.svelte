<script module lang="ts">
    import {
        create as createUser,
        edit as editUser,
        index as usersIndex,
    } from '@/routes/users';

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
    import { untrack } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';
    import UserPlus from '@lucide/svelte/icons/user-plus';
    import AppHead from '@/components/AppHead.svelte';
    import SearchInput from '@/components/SearchInput.svelte';
    import TablePagination from '@/components/TablePagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Table,
        TableBody,
        TableCell,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table';
    import type { Paginated } from '@/types';

    type UserRow = {
        id: number;
        name: string;
        email: string;
        roles: string[];
        created_at: string | null;
    };

    let {
        users,
        filters,
    }: {
        users: Paginated<UserRow>;
        filters: { search?: string | null };
    } = $props();

    let search = $state(untrack(() => filters.search ?? ''));

    function submitSearch(event: Event): void {
        event.preventDefault();

        router.get(
            usersIndex({
                query: {
                    search: search || undefined,
                },
            }),
            { preserveState: true, replace: true },
        );
    }
</script>

<AppHead title="Users" />

<h1 class="sr-only">Users</h1>

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-xl font-semibold tracking-tight">Users</h2>
            <p class="text-sm text-muted-foreground">
                Accounts that can sign in, with the roles they hold.
            </p>
        </div>

        <Button size="sm" asChild>
            {#snippet children(props)}
                <Link href={createUser()} class={props.class}>
                    <UserPlus class="size-4" />
                    New user
                </Link>
            {/snippet}
        </Button>
    </div>

    <form onsubmit={submitSearch} class="max-w-sm">
        <SearchInput
            bind:value={search}
            name="search"
            placeholder="Search name or email"
            ariaLabel="Search users"
        />
    </form>

    <div class="rounded-lg border border-border/70">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Roles</TableHead>
                    <TableHead>Created</TableHead>
                    <TableHead class="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#each (users as Paginated<UserRow>).data as user (user.id)}
                    <TableRow>
                        <TableCell class="font-medium">{user.name}</TableCell>
                        <TableCell class="text-muted-foreground">
                            {user.email}
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap gap-1">
                                {#each user.roles as role (role)}
                                    <Badge variant="secondary">{role}</Badge>
                                {/each}
                            </div>
                        </TableCell>
                        <TableCell class="tabular-nums text-muted-foreground">
                            {user.created_at}
                        </TableCell>
                        <TableCell class="text-right">
                            <Button variant="outline" size="sm" asChild>
                                {#snippet children(props)}
                                    <Link
                                        href={editUser(user.id)}
                                        class={props.class}
                                    >
                                        Edit
                                    </Link>
                                {/snippet}
                            </Button>
                        </TableCell>
                    </TableRow>
                {:else}
                    <TableRow>
                        <TableCell colspan={5} class="h-24 text-center text-muted-foreground">
                            No users found.
                        </TableCell>
                    </TableRow>
                {/each}
            </TableBody>
        </Table>
    </div>

    <TablePagination meta={users as Paginated<UserRow>} />
</div>
