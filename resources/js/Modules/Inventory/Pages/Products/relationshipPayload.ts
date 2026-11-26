type Relationships = {
    tags: string[];
    accessories: { accessory_product_id: string | number | null; quantity: string | number; type: string; auto_add: boolean; notes: string | null }[];
    alternatives: { product_id: string | number | null }[];
};

const sorted = (values: unknown[]) => values.map((value) => JSON.stringify(value)).sort();

export function relationshipSnapshot(data: Relationships) {
    return {
        tags: sorted(data.tags),
        accessories: sorted(data.accessories.map((row) => ({
            id: row.accessory_product_id === null ? null : String(row.accessory_product_id),
            quantity: Number(row.quantity),
            type: row.type,
            auto_add: row.auto_add,
            notes: row.notes || null,
        }))),
        alternatives: sorted(data.alternatives.map((row) => row.product_id === null ? null : String(row.product_id))),
    };
}

export function relationshipPayload<T extends Relationships>(data: T, initial: ReturnType<typeof relationshipSnapshot>, isEdit: boolean, canViewProducts: boolean): Partial<T> {
    const result: Partial<T> = { ...data };
    const current = relationshipSnapshot(data);
    if (isEdit && JSON.stringify(current.tags) === JSON.stringify(initial.tags)) delete result.tags;
    if (!canViewProducts || (isEdit && JSON.stringify(current.accessories) === JSON.stringify(initial.accessories))) delete result.accessories;
    if (!canViewProducts || (isEdit && JSON.stringify(current.alternatives) === JSON.stringify(initial.alternatives))) delete result.alternatives;
    return result;
}
