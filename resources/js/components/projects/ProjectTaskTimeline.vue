<script setup lang="ts">
import { computed } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { ProjectTaskTimelineItem } from '@/pages/projects/columns';
import {
    taskTimelineCircleClass,
    taskTimelineSegmentClass,
} from '@/pages/projects/columns';

const props = defineProps<{
    tasks: ProjectTaskTimelineItem[];
}>();

const circlePosition = (index: number, total: number): number => {
    if (total === 1) {
        return 50;
    }

    return ((index + 1) / (total + 1)) * 100;
};

const positions = computed(() =>
    props.tasks.map((_, index) => circlePosition(index, props.tasks.length)),
);

const segments = computed(() =>
    props.tasks.map((task, index) => {
        const end = positions.value[index];
        const start = index === 0 ? 0 : positions.value[index - 1];

        return {
            left: start,
            width: end - start,
            status: task.status,
        };
    }),
);
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <div class="relative h-8 w-full px-1">
            <div
                class="absolute top-1/2 right-1 left-1 h-0.5 -translate-y-1/2 rounded-full bg-red-500/25"
            />

            <div
                v-for="(segment, index) in segments"
                :key="`segment-${index}`"
                class="absolute top-1/2 h-0.5 -translate-y-1/2 rounded-full transition-colors"
                :class="taskTimelineSegmentClass(segment.status)"
                :style="{
                    left: `calc(${segment.left}% + 0.25rem)`,
                    width: `calc(${segment.width}% - 0.25rem)`,
                }"
            />

            <Tooltip
                v-for="(task, index) in tasks"
                :key="task.id"
            >
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        class="absolute top-1/2 z-10 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-background shadow-sm transition-all duration-200 hover:scale-125 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="taskTimelineCircleClass(task.status)"
                        :style="{ left: `${positions[index]}%` }"
                        :aria-label="task.name"
                    />
                </TooltipTrigger>
                <TooltipContent side="top">
                    {{ task.name }}
                </TooltipContent>
            </Tooltip>
        </div>
    </TooltipProvider>
</template>
