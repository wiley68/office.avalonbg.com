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
        return 'Изберете начална дата.';
    }

    if (!dateTo) {
        return 'Изберете крайна дата.';
    }

    if (dateTo < dateFrom) {
        return 'Крайната дата трябва да е след или равна на началната.';
    }

    return null;
}
