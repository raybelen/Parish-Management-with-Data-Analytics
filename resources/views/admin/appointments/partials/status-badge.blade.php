<x-admin.status :value="$status" :pending-label="$appointment->requiresOfficeApproval() ? 'Awaiting approval' : 'Awaiting documents'" />
