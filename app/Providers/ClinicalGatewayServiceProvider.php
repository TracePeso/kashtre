<?php

namespace App\Providers;

use App\Contracts\Clinical\AiUseCaseGateway;
use App\Contracts\Clinical\AuditTrailGateway;
use App\Contracts\Clinical\CareAccessGateway;
use App\Contracts\Clinical\ClientSpaceAssignmentGateway;
use App\Contracts\Clinical\PrivilegeGateway;
use App\Contracts\Clinical\DelegationGateway;
use App\Contracts\Clinical\SensitivityRestrictionGateway;
use App\Contracts\Clinical\PermissionCatalogGateway;
use App\Contracts\Clinical\IdentityConfirmationGateway;
use App\Contracts\Clinical\IdentityConcernGateway;
use App\Contracts\Clinical\EncounterGateway;
use App\Contracts\Clinical\PatientWorkspaceGateway;
use App\Contracts\Clinical\ContentGovernanceGateway;
use App\Contracts\Clinical\EngagementGateway;
use App\Contracts\Clinical\InteroperabilityGateway;
use App\Contracts\Clinical\CareTransitionsGateway;
use App\Contracts\Clinical\ClinicalDictionaryGateway;
use App\Contracts\Clinical\ClinicalSettingsGateway;
use App\Contracts\Clinical\ConsumptionGateway;
use App\Contracts\Clinical\CrashCartReconciliationGateway;
use App\Contracts\Clinical\CriticalAlertsGateway;
use App\Contracts\Clinical\EncounterSectionGateway;
use App\Contracts\Clinical\EntitlementGateway;
use App\Contracts\Clinical\FhirExportGateway;
use App\Contracts\Clinical\HandoverGateway;
use App\Contracts\Clinical\HandoverRecordGateway;
use App\Contracts\Clinical\DiagnosticReportStatusGateway;
use App\Contracts\Clinical\MedicationAdverseEventGateway;
use App\Contracts\Clinical\MedicationReconciliationGateway;
use App\Contracts\Clinical\TriageGateway;
use App\Contracts\Clinical\DiagnosesGateway;
use App\Contracts\Clinical\MarGateway;
use App\Contracts\Clinical\MaternityGateway;
use App\Contracts\Clinical\MedicationOrdersGateway;
use App\Contracts\Clinical\ObservationsGateway;
use App\Contracts\Clinical\PatientWorklistGateway;
use App\Contracts\Clinical\ProcessExecutionGateway;
use App\Contracts\Clinical\ProvisioningGateway;
use App\Contracts\Clinical\RecallGateway;
use App\Contracts\Clinical\ScratchpadGateway;
use App\Contracts\Clinical\TaskVisibilityGateway;
use App\Contracts\Clinical\WardCensusGateway;
use App\Contracts\Clinical\WorkOrderGateway;
use App\Services\Clinical\Gateways\Api\ApiAiUseCaseGateway;
use App\Services\Clinical\Gateways\Api\ApiAuditTrailGateway;
use App\Services\Clinical\Gateways\Api\ApiCareAccessGateway;
use App\Services\Clinical\Gateways\Api\ApiClientSpaceAssignmentGateway;
use App\Services\Clinical\Gateways\Api\ApiPrivilegeGateway;
use App\Services\Clinical\Gateways\Api\ApiDelegationGateway;
use App\Services\Clinical\Gateways\Api\ApiSensitivityRestrictionGateway;
use App\Services\Clinical\Gateways\Api\ApiPermissionCatalogGateway;
use App\Services\Clinical\Gateways\Api\ApiIdentityConfirmationGateway;
use App\Services\Clinical\Gateways\Api\ApiIdentityConcernGateway;
use App\Services\Clinical\Gateways\Api\ApiEncounterGateway;
use App\Services\Clinical\Gateways\Api\ApiPatientWorkspaceGateway;
use App\Services\Clinical\Gateways\Api\ApiContentGovernanceGateway;
use App\Services\Clinical\Gateways\Api\ApiEngagementGateway;
use App\Services\Clinical\Gateways\Api\ApiInteroperabilityGateway;
use App\Services\Clinical\Gateways\Api\ApiCareTransitionsGateway;
use App\Services\Clinical\Gateways\Api\ApiClinicalSettingsGateway;
use App\Services\Clinical\Gateways\Api\ApiConsumptionGateway;
use App\Services\Clinical\Gateways\Api\ApiCrashCartReconciliationGateway;
use App\Services\Clinical\Gateways\Api\ApiCriticalAlertsGateway;
use App\Services\Clinical\Gateways\Api\ApiEncounterSectionGateway;
use App\Services\Clinical\Gateways\Api\ApiEntitlementGateway;
use App\Services\Clinical\Gateways\Api\ApiFhirExportGateway;
use App\Services\Clinical\Gateways\Api\ApiHandoverGateway;
use App\Services\Clinical\Gateways\Api\ApiHandoverRecordGateway;
use App\Services\Clinical\Gateways\Api\ApiDiagnosticReportStatusGateway;
use App\Services\Clinical\Gateways\Api\ApiMedicationAdverseEventGateway;
use App\Services\Clinical\Gateways\Api\ApiMedicationReconciliationGateway;
use App\Services\Clinical\Gateways\Api\ApiTriageGateway;
use App\Services\Clinical\Gateways\Api\ApiDiagnosesGateway;
use App\Services\Clinical\Gateways\Api\ApiDictionaryGateway;
use App\Services\Clinical\Gateways\Api\ApiMarGateway;
use App\Services\Clinical\Gateways\Api\ApiMaternityGateway;
use App\Services\Clinical\Gateways\Api\ApiMedicationOrdersGateway;
use App\Services\Clinical\Gateways\Api\ApiObservationsGateway;
use App\Services\Clinical\Gateways\Api\ApiPatientWorklistGateway;
use App\Services\Clinical\Gateways\Api\ApiProcessExecutionGateway;
use App\Services\Clinical\Gateways\Api\ApiProvisioningGateway;
use App\Services\Clinical\Gateways\Api\ApiRecallGateway;
use App\Services\Clinical\Gateways\Api\ApiScratchpadGateway;
use App\Services\Clinical\Gateways\Api\ApiTaskVisibilityGateway;
use App\Services\Clinical\Gateways\Api\ApiWardCensusGateway;
use App\Services\Clinical\Gateways\Api\ApiWorkOrderGateway;
use App\Services\Clinical\Gateways\Local\LocalAiUseCaseGateway;
use App\Services\Clinical\Gateways\Local\LocalAuditTrailGateway;
use App\Services\Clinical\Gateways\Local\LocalCareAccessGateway;
use App\Services\Clinical\Gateways\Local\LocalClientSpaceAssignmentGateway;
use App\Services\Clinical\Gateways\Local\LocalPrivilegeGateway;
use App\Services\Clinical\Gateways\Local\LocalDelegationGateway;
use App\Services\Clinical\Gateways\Local\LocalSensitivityRestrictionGateway;
use App\Services\Clinical\Gateways\Local\LocalPermissionCatalogGateway;
use App\Services\Clinical\Gateways\Local\LocalIdentityConfirmationGateway;
use App\Services\Clinical\Gateways\Local\LocalIdentityConcernGateway;
use App\Services\Clinical\Gateways\Local\LocalEncounterGateway;
use App\Services\Clinical\Gateways\Local\LocalPatientWorkspaceGateway;
use App\Services\Clinical\Gateways\Local\LocalContentGovernanceGateway;
use App\Services\Clinical\Gateways\Local\LocalEngagementGateway;
use App\Services\Clinical\Gateways\Local\LocalInteroperabilityGateway;
use App\Services\Clinical\Gateways\Local\LocalCareTransitionsGateway;
use App\Services\Clinical\Gateways\Local\LocalClinicalSettingsGateway;
use App\Services\Clinical\Gateways\Local\LocalConsumptionGateway;
use App\Services\Clinical\Gateways\Local\LocalCrashCartReconciliationGateway;
use App\Services\Clinical\Gateways\Local\LocalCriticalAlertsGateway;
use App\Services\Clinical\Gateways\Local\LocalEncounterSectionGateway;
use App\Services\Clinical\Gateways\Local\LocalEntitlementGateway;
use App\Services\Clinical\Gateways\Local\LocalFhirExportGateway;
use App\Services\Clinical\Gateways\Local\LocalHandoverGateway;
use App\Services\Clinical\Gateways\Local\LocalHandoverRecordGateway;
use App\Services\Clinical\Gateways\Local\LocalDiagnosticReportStatusGateway;
use App\Services\Clinical\Gateways\Local\LocalMedicationAdverseEventGateway;
use App\Services\Clinical\Gateways\Local\LocalMedicationReconciliationGateway;
use App\Services\Clinical\Gateways\Local\LocalTriageGateway;
use App\Services\Clinical\Gateways\Local\LocalDiagnosesGateway;
use App\Services\Clinical\Gateways\Local\LocalDictionaryGateway;
use App\Services\Clinical\Gateways\Local\LocalMarGateway;
use App\Services\Clinical\Gateways\Local\LocalMaternityGateway;
use App\Services\Clinical\Gateways\Local\LocalMedicationOrdersGateway;
use App\Services\Clinical\Gateways\Local\LocalObservationsGateway;
use App\Services\Clinical\Gateways\Local\LocalPatientWorklistGateway;
use App\Services\Clinical\Gateways\Local\LocalProcessExecutionGateway;
use App\Services\Clinical\Gateways\Local\LocalProvisioningGateway;
use App\Services\Clinical\Gateways\Local\LocalRecallGateway;
use App\Services\Clinical\Gateways\Local\LocalScratchpadGateway;
use App\Services\Clinical\Gateways\Local\LocalTaskVisibilityGateway;
use App\Services\Clinical\Gateways\Local\LocalWardCensusGateway;
use App\Services\Clinical\Gateways\Local\LocalWorkOrderGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * The strangler switch for the Clinical Module split.
 *
 * CLINICAL_DRIVER=local  → in-process engines against the clinical_* tables
 * CLINICAL_DRIVER=api    → HTTP calls to CLINICAL_ORCHESTRATOR
 *
 * Every consumer depends on the interface, so flipping this env var moves the
 * whole clinical surface between implementations without touching a caller.
 * That is what makes the cutover reversible: if the remote service misbehaves
 * in production, setting the driver back to `local` restores the previous
 * behaviour in one deploy rather than one revert.
 *
 * The two are not perfectly equivalent, and the differences are documented on
 * the individual gateways — chiefly that the API driver lets Clinical own
 * consumption-fact emission, ReBAC enforcement and break-glass windows, while
 * the local driver performs all three itself.
 */
class ClinicalGatewayServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array{local: class-string, api: class-string}>
     */
    private const GATEWAYS = [
        ObservationsGateway::class => [
            'local' => LocalObservationsGateway::class,
            'api' => ApiObservationsGateway::class,
        ],
        ClientSpaceAssignmentGateway::class => [
            'local' => LocalClientSpaceAssignmentGateway::class,
            'api' => ApiClientSpaceAssignmentGateway::class,
        ],
        PrivilegeGateway::class => [
            'local' => LocalPrivilegeGateway::class,
            'api' => ApiPrivilegeGateway::class,
        ],
        DelegationGateway::class => [
            'local' => LocalDelegationGateway::class,
            'api' => ApiDelegationGateway::class,
        ],
        SensitivityRestrictionGateway::class => [
            'local' => LocalSensitivityRestrictionGateway::class,
            'api' => ApiSensitivityRestrictionGateway::class,
        ],
        PermissionCatalogGateway::class => [
            'local' => LocalPermissionCatalogGateway::class,
            'api' => ApiPermissionCatalogGateway::class,
        ],
        IdentityConfirmationGateway::class => [
            'local' => LocalIdentityConfirmationGateway::class,
            'api' => ApiIdentityConfirmationGateway::class,
        ],
        IdentityConcernGateway::class => [
            'local' => LocalIdentityConcernGateway::class,
            'api' => ApiIdentityConcernGateway::class,
        ],
        EncounterGateway::class => [
            'local' => LocalEncounterGateway::class,
            'api' => ApiEncounterGateway::class,
        ],
        PatientWorkspaceGateway::class => [
            'local' => LocalPatientWorkspaceGateway::class,
            'api' => ApiPatientWorkspaceGateway::class,
        ],
        EntitlementGateway::class => [
            'local' => LocalEntitlementGateway::class,
            'api' => ApiEntitlementGateway::class,
        ],
        CrashCartReconciliationGateway::class => [
            'local' => LocalCrashCartReconciliationGateway::class,
            'api' => ApiCrashCartReconciliationGateway::class,
        ],
        CareAccessGateway::class => [
            'local' => LocalCareAccessGateway::class,
            'api' => ApiCareAccessGateway::class,
        ],
        CareTransitionsGateway::class => [
            'local' => LocalCareTransitionsGateway::class,
            'api' => ApiCareTransitionsGateway::class,
        ],
        ContentGovernanceGateway::class => [
            'local' => LocalContentGovernanceGateway::class,
            'api' => ApiContentGovernanceGateway::class,
        ],
        AiUseCaseGateway::class => [
            'local' => LocalAiUseCaseGateway::class,
            'api' => ApiAiUseCaseGateway::class,
        ],
        InteroperabilityGateway::class => [
            'local' => LocalInteroperabilityGateway::class,
            'api' => ApiInteroperabilityGateway::class,
        ],
        EngagementGateway::class => [
            'local' => LocalEngagementGateway::class,
            'api' => ApiEngagementGateway::class,
        ],
        MedicationOrdersGateway::class => [
            'local' => LocalMedicationOrdersGateway::class,
            'api' => ApiMedicationOrdersGateway::class,
        ],
        MarGateway::class => [
            'local' => LocalMarGateway::class,
            'api' => ApiMarGateway::class,
        ],
        ClinicalDictionaryGateway::class => [
            'local' => LocalDictionaryGateway::class,
            'api' => ApiDictionaryGateway::class,
        ],
        ScratchpadGateway::class => [
            'local' => LocalScratchpadGateway::class,
            'api' => ApiScratchpadGateway::class,
        ],
        ClinicalSettingsGateway::class => [
            'local' => LocalClinicalSettingsGateway::class,
            'api' => ApiClinicalSettingsGateway::class,
        ],
        DiagnosesGateway::class => [
            'local' => LocalDiagnosesGateway::class,
            'api' => ApiDiagnosesGateway::class,
        ],
        PatientWorklistGateway::class => [
            'local' => LocalPatientWorklistGateway::class,
            'api' => ApiPatientWorklistGateway::class,
        ],
        WardCensusGateway::class => [
            'local' => LocalWardCensusGateway::class,
            'api' => ApiWardCensusGateway::class,
        ],
        ProcessExecutionGateway::class => [
            'local' => LocalProcessExecutionGateway::class,
            'api' => ApiProcessExecutionGateway::class,
        ],
        AuditTrailGateway::class => [
            'local' => LocalAuditTrailGateway::class,
            'api' => ApiAuditTrailGateway::class,
        ],
        ConsumptionGateway::class => [
            'local' => LocalConsumptionGateway::class,
            'api' => ApiConsumptionGateway::class,
        ],
        HandoverGateway::class => [
            'local' => LocalHandoverGateway::class,
            'api' => ApiHandoverGateway::class,
        ],
        HandoverRecordGateway::class => [
            'local' => LocalHandoverRecordGateway::class,
            'api' => ApiHandoverRecordGateway::class,
        ],
        MedicationReconciliationGateway::class => [
            'local' => LocalMedicationReconciliationGateway::class,
            'api' => ApiMedicationReconciliationGateway::class,
        ],
        MedicationAdverseEventGateway::class => [
            'local' => LocalMedicationAdverseEventGateway::class,
            'api' => ApiMedicationAdverseEventGateway::class,
        ],
        DiagnosticReportStatusGateway::class => [
            'local' => LocalDiagnosticReportStatusGateway::class,
            'api' => ApiDiagnosticReportStatusGateway::class,
        ],
        TriageGateway::class => [
            'local' => LocalTriageGateway::class,
            'api' => ApiTriageGateway::class,
        ],
        CriticalAlertsGateway::class => [
            'local' => LocalCriticalAlertsGateway::class,
            'api' => ApiCriticalAlertsGateway::class,
        ],
        FhirExportGateway::class => [
            'local' => LocalFhirExportGateway::class,
            'api' => ApiFhirExportGateway::class,
        ],
        ProvisioningGateway::class => [
            'local' => LocalProvisioningGateway::class,
            'api' => ApiProvisioningGateway::class,
        ],
        TaskVisibilityGateway::class => [
            'local' => LocalTaskVisibilityGateway::class,
            'api' => ApiTaskVisibilityGateway::class,
        ],
        EncounterSectionGateway::class => [
            'local' => LocalEncounterSectionGateway::class,
            'api' => ApiEncounterSectionGateway::class,
        ],
        WorkOrderGateway::class => [
            'local' => LocalWorkOrderGateway::class,
            'api' => ApiWorkOrderGateway::class,
        ],
        RecallGateway::class => [
            'local' => LocalRecallGateway::class,
            'api' => ApiRecallGateway::class,
        ],
        MaternityGateway::class => [
            'local' => LocalMaternityGateway::class,
            'api' => ApiMaternityGateway::class,
        ],
    ];

    public function register(): void
    {
        $driver = $this->driver();

        foreach (self::GATEWAYS as $contract => $implementations) {
            $this->app->bind($contract, $implementations[$driver]);
        }
    }

    /**
     * Selecting `api` without a URL would fail every clinical action at
     * runtime with a 503 that looks like an outage rather than a
     * misconfiguration. Falling back to `local` keeps the system working and
     * says loudly why — a half-configured deploy should degrade to the
     * behaviour that still works, not to a dead clinical module.
     */
    private function driver(): string
    {
        $driver = (string) config('services.clinical.driver', 'local');

        if (! array_key_exists($driver, ['local' => true, 'api' => true])) {
            Log::warning("Unknown CLINICAL_DRIVER [{$driver}]; falling back to local.");

            return 'local';
        }

        if ($driver === 'api' && empty(config('services.clinical.url'))) {
            Log::error('CLINICAL_DRIVER=api but CLINICAL_MODULE_URL is empty; falling back to local.');

            return 'local';
        }

        return $driver;
    }
}
