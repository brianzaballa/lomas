<script lang="ts">
    export let groupedLoansByArea: Record<string, number> = {};
</script>

<div class="rounded-lg border bg-card p-4">
    <div class="mb-3 text-sm font-medium text-muted-foreground">Loans by area</div>

    <div class="space-y-2">
        {#if Object.keys(groupedLoansByArea).length === 0}
            <div class="text-sm text-muted-foreground">No loans yet.</div>
        {:else}
            {#each Object.entries(groupedLoansByArea) as [area, count]}
                <div class="flex items-center justify-between text-sm">
                    <span>{area}</span>
                    <span class="font-medium">{count}</span>
                </div>
            {/each}
        {/if}
    </div>
</div>
