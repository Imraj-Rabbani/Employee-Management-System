<?php

namespace App\Modules\Workflow\Contracts;

use App\Modules\Workflow\Models\WorkflowStep;

/**
 * Turns one workflow step ("the supervisor", "anyone with role X" ...) into real people.
 * One class per ApproverType, picked by WorkflowEngine::resolverFor().
 */
interface ResolvesApprovers
{
    /**
     * @param  int|null  $requesterUserId  the login who submitted; never asked to approve their own document
     * @return list<int> central users.id of everyone who must answer this step
     */
    public function approvers(WorkflowStep $step, Approvable $document, ?int $requesterUserId): array;
}
