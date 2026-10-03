<?php

namespace Database\Seeders;

use App\Domain\Nodes\NodeIdentity;
use App\Models\Node;
use Illuminate\Database\Seeder;

/**
 * Registers the node this installation runs as.
 *
 * The code comes from config/lomas.php, so a fresh install identifies itself
 * correctly without being told. Idempotent, so re-seeding never changes the
 * identity of a node that is already in service. See AGENTS.md section 69.2.
 */
class NodeSeeder extends Seeder
{
    public function __construct(private readonly NodeIdentity $identity) {}

    public function run(): void
    {
        if (Node::query()->where('code', $this->identity->code())->exists()) {
            return;
        }

        $this->identity->node();
    }
}
