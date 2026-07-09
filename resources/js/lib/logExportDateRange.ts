export type LogExportDateRange = {
    dateFrom: string;
    dateTo: string;
};

function formatDateInput(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function getDefaultLogExportDateRange(): LogExportDateRange {
    const dateTo = new Date();
    const dateFrom = new Date();
    dateFrom.setDate(dateFrom.getDate() - 30);

    return {
        dateFrom: formatDateInput(dateFrom),
        dateTo: formatDateInput(dateTo),
    };
}

export function validateLogExportDateRange(
    dateFrom: string,
    dateTo: string,
): string | null {
    if (!dateFrom) {
        return 'audit_logs.export.validation.date_from_required';
    }

    if (!dateTo) {
        return 'audit_logs.export.validation.date_to_required';
    }

    if (dateTo < dateFrom) {
        return 'audit_logs.export.validation.date_range_invalid';
    }

    return null;
}
