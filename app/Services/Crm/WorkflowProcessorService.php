<?php

namespace App\Services\Crm;

use App\Models\Crm\ExternalLead;
use App\Models\Crm\Workflow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WorkflowProcessorService
{
    /**
     * Encuentra el primer workflow activo que coincide con las condiciones de un lead.
     *
     * @param ExternalLead $lead
     * @return Workflow|null
     */
    public function findMatchingWorkflow(ExternalLead $lead): ?Workflow
    {
        $workflows = Workflow::where('is_active', true)->orderBy('priority')->get();

        foreach ($workflows as $workflow) {
            if ($this->checkWorkflowConditions($lead, $workflow)) {
                return $workflow;
            }
        }
        return null;
    }

    private function checkWorkflowConditions(ExternalLead $lead, Workflow $workflow): bool
    {
        $conditions = $workflow->conditions;
        
        // Si no hay condiciones, el workflow siempre coincide.
        if ($conditions->isEmpty()) {
            return true;
        }

        // Agrupamos las condiciones por su identificador de grupo.
        $conditionGroups = $conditions->groupBy('group_identifier');

        // Lógica principal: TODAS las agrupaciones de condiciones deben ser verdaderas.
        foreach ($conditionGroups as $group) {
            if (!$this->evaluateConditionGroup($lead, $group)) {
                return false; // Si un grupo falla, el workflow entero falla.
            }
        }

        return true; // Todos los grupos de condiciones fueron exitosos.
    }

    private function evaluateConditionGroup(ExternalLead $lead, Collection $group): bool
    {
        $logic = $group->first()->group_logic ?? 'AND'; // Por defecto, un grupo es AND

        foreach ($group as $condition) {
            $payloadValue = data_get($lead->payload, $condition->field);
            $evaluationResult = $this->evaluate($payloadValue, $condition->operator, $condition->value);

            // Lógica AND: la primera que falle hace que el grupo falle.
            if ($logic === 'AND' && !$evaluationResult) {
                return false;
            }

            // Lógica OR: la primera que sea exitosa hace que el grupo sea exitoso.
            if ($logic === 'OR' && $evaluationResult) {
                return true;
            }
        }

        // Si hemos llegado hasta aquí:
        // Para un grupo AND, significa que todas fueron verdaderas.
        // Para un grupo OR, significa que todas fallaron.
        return $logic === 'AND';
    }

    /**
     * Evalúa una condición individual.
     *
     * @param mixed $actualValue
     * @param string $operator
     * @param string $expectedValue
     * @return bool
     */
    private function evaluate($actualValue, string $operator, string $expectedValue): bool
    {
        Log::info("Evaluando: {$actualValue} {$operator} {$expectedValue}");
        switch ($operator) {
            case 'equals':
                return $actualValue == $expectedValue;
            case 'not_equals':
                return $actualValue != $expectedValue;
            case 'contains':
                return is_string($actualValue) && str_contains(strtolower($actualValue), strtolower($expectedValue));
            case 'starts_with':
                return is_string($actualValue) && str_starts_with(strtolower($actualValue), strtolower($expectedValue));
            // Puedes añadir más operadores aquí: 'greater_than', 'less_than', etc.
            default:
                Log::warning("Operador no reconocido: {$operator}");
                return false;
        }
    }
}