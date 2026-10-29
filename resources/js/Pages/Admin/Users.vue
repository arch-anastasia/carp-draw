<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/Layouts/AppLayout.vue'

interface AdminUser {
    id: string
    name: string
    email: string
    created_at: string
    is_admin: boolean
    subscribed: boolean
}

defineProps<{
    users: {
        data: AdminUser[]
        current_page: number
        last_page: number
    }
    stats: { total: number; subscribers: number; weekly: number }
}>()
</script>

<template>
    <AppLayout title="Admin">
        <div class="admin-page app-page-narrow">
            <div class="app-page-heading">
                <div>
                    <span class="eyebrow">Administration</span>
                    <h1>Users</h1>
                    <p>Accounts, subscriptions and weekly signups.</p>
                </div>
            </div>
            <div class="hero-proof" aria-label="Workspace metrics">
                <div>
                    <strong>{{ stats.total }}</strong>
                    <span>total users</span>
                </div>
                <div>
                    <strong>{{ stats.subscribers }}</strong>
                    <span>subscribers</span>
                </div>
                <div>
                    <strong>{{ stats.weekly }}</strong>
                    <span>new this week</span>
                </div>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-users-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th>Admin</th>
                            <th>Subscribed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.data" :key="user.id">
                            <td>{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td>{{ user.created_at }}</td>
                            <td>{{ user.is_admin ? 'Yes' : '—' }}</td>
                            <td>{{ user.subscribed ? 'Yes' : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">
                <Link
                    v-if="users.current_page > 1"
                    class="secondary-button"
                    :href="route('admin.users.index', { page: users.current_page - 1 })"
                >
                    Previous
                </Link>
                <span>Page {{ users.current_page }} of {{ users.last_page }}</span>
                <Link
                    v-if="users.current_page < users.last_page"
                    class="secondary-button"
                    :href="route('admin.users.index', { page: users.current_page + 1 })"
                >
                    Next
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.admin-table-wrap {
    overflow-x: auto;
}

.admin-users-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.admin-users-table th,
.admin-users-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--line);
    text-align: left;
}

.admin-users-table th {
    color: var(--muted);
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.admin-pagination {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 18px;
}

.admin-pagination span {
    color: var(--muted);
    font-size: 0.85rem;
}
</style>
