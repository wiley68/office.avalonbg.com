export type ProfitTypeKind = 'income' | 'expense';

export type ProfitTypeOption = {
    id: number;
    name: string;
    kind: ProfitTypeKind;
};

export type ProfitEntryItem = {
    id: number;
    date: string;
    document_number: string | null;
    description: string | null;
    amount: string;
    profit_type_id: number;
    type: {
        id: number;
        name: string;
        kind: ProfitTypeKind;
    };
};

export type ProfitMonthTotals = {
    income: string;
    expense: string;
    result: string;
};

export type ProfitMonthResponse = {
    month: string;
    period: {
        from: string;
        to: string;
    };
    income: ProfitEntryItem[];
    expense: ProfitEntryItem[];
    totals: ProfitMonthTotals;
};

export type ProfitStatsMonth = {
    month: string;
    income: string;
    expense: string;
    result: string;
};

export type ProfitStatsResponse = {
    period: {
        from: string;
        to: string;
    };
    months: ProfitStatsMonth[];
    totals: ProfitMonthTotals;
};
