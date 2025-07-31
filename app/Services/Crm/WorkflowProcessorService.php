<?php

namespace App\Services\Crm;

use App\Models\Crm\ExternalLead;
use App\Models\Crm\Workflow;

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
        // Obtener todos los workflows activos, ordenados por prioridad
        $workflows = Workflow::where('is_active', true)->orderBy('priority')->get();

        foreach ($workflows as $workflow) {
            if ($this->checkConditions($lead, $workflow)) {
                return $workflow; // Devuelve el primer workflow que coincida
            }
        }

        return null; // Ningún workflow coincidió
    }

    /**
     * Verifica si un lead cumple con todas las condiciones de un workflow.
     *
     * @param ExternalLead $lead
     * @param Workflow $workflow
     * @return bool
     */
    private function checkConditions(ExternalLead $lead, Workflow $workflow): bool
    {
        // Por ahora, implementamos una lógica simple de "TODAS las condiciones deben cumplirse (AND)"
        // En el futuro, se puede expandir para manejar grupos y condiciones OR.
        foreach ($workflow->conditions as $condition) {
            $payloadValue = data_get($lead->payload, $condition->field); // Permite acceder a datos anidados ej: user.name

            if (!$this->evaluate($payloadValue, $condition->operator, $condition->value)) {
                return false; // Si una sola condición falla, todo el workflow falla
            }
        }

        return true; // Todas las condiciones se cumplieron
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
                return false;
        }
    }
}