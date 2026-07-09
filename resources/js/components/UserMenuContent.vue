<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { IdCard, LogOut, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useTranslations } from '@/composables/useTranslations';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { index as usersIndex } from '@/routes/users';
import type { User } from '@/types';

type Props = {
    user: User;
};

const props = defineProps<Props>();

const { t } = useTranslations();

const usersMenuLabel = computed(() => {
    if (props.user.is_profiler) {
        return t('users.admin.plural');
    }

    if (props.user.is_admin) {
        return t('users.office_user.plural');
    }

    return null;
});

const showUsersMenu = computed(() => usersMenuLabel.value !== null);

const handleLogout = (): void => {
    router.flushAll();
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup v-if="showUsersMenu">
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="usersIndex()"
                prefetch
            >
                <Users class="mr-2 h-4 w-4" />
                {{ usersMenuLabel }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator v-if="showUsersMenu" />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="profileEdit()"
                prefetch
            >
                <IdCard class="mr-2 h-4 w-4" />
                Profile
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
