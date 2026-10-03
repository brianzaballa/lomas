<?php

namespace App\Domain\Nodes;

use App\Models\Node;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use LogicException;

/**
 * The identity of the installation the code is running on.
 *
 * The node code lives in config/lomas.php and not in the database, so a fresh
 * install can never come up with the wrong identity, and the code is never
 * editable through the application. See AGENTS.md section 69.2.
 */
class NodeIdentity
{
    /**
     * Uppercase letters and digits only, so a code is safe inside a business
     * number and on a printed document.
     */
    protected const CODE_PATTERN = '/^[A-Z0-9]{2,16}$/';

    /**
     * The node code, e.g. "MNL1".
     */
    public function code(): string
    {
        $code = config('lomas.node.code');

        if (! is_string($code) || preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new InvalidArgumentException(
                'config/lomas.php node code must be 2 to 16 uppercase letters or digits.',
            );
        }

        return $code;
    }

    public function name(): string
    {
        $name = config('lomas.node.name');

        return is_string($name) && trim($name) !== '' ? $name : $this->code();
    }

    public function role(): string
    {
        $role = config('lomas.node.role');

        return in_array($role, [Node::ROLE_CENTRAL, Node::ROLE_BRANCH], true)
            ? $role
            : Node::ROLE_BRANCH;
    }

    public function timezone(): string
    {
        $timezone = config('lomas.node.timezone');

        return is_string($timezone) && trim($timezone) !== '' ? $timezone : 'UTC';
    }

    public function isCentral(): bool
    {
        return $this->role() === Node::ROLE_CENTRAL;
    }

    /**
     * The row of this installation in the nodes table.
     *
     * It is created on first use so a freshly migrated installation identifies
     * itself without an extra step, and it is never overwritten: an operator
     * changing the code in config does not repurpose a node that already issued
     * business numbers.
     */
    public function node(): Node
    {
        $code = $this->code();

        $node = Node::query()->where('code', $code)->first();

        if ($node instanceof Node) {
            return $node;
        }

        if (Node::query()->exists()) {
            throw new LogicException(
                'This installation already acts as another node. The node code is permanent because it is written into every business number and audit entry. Restore the original code in config/lomas.php.',
            );
        }

        return Node::query()->create([
            'code' => $code,
            'name' => $this->name(),
            'role' => $this->role(),
            'timezone' => $this->timezone(),
            'is_active' => true,
            'last_applied_serial' => 0,
            'last_seen_at' => Date::now(),
        ]);
    }

    /**
     * The uuid of this installation, used as node_id on every record it writes.
     */
    public function id(): string
    {
        return $this->node()->getKey();
    }
}
