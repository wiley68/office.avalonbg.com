<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';

const model = defineModel<string>({ required: true });
defineProps<{ groups: { id: number; name: string }[]; error?: string }>();
const { t } = useTranslations();
</script>

<template>
    <div class="space-y-2">
        <Label for="group-id">{{ t('groups.singular') }} *</Label>
        <Select v-model="model" required>
            <SelectTrigger
                id="group-id"
                :aria-invalid="!!error"
                aria-describedby="group-id-help group-id-error"
            >
                <SelectValue :placeholder="t('groups.select')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="group in groups"
                    :key="group.id"
                    :value="String(group.id)"
                    >{{ group.name }}</SelectItem
                >
            </SelectContent>
        </Select>
        <p id="group-id-help" class="text-xs text-muted-foreground">
            {{
                groups.length
                    ? t('groups.assignment_hint')
                    : t('groups.create_first')
            }}
        </p>
        <InputError id="group-id-error" :message="error" />
    </div>
</template>
