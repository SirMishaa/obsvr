import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    name: string;
    email: string;
    auth_provider: 'twitch' | null;
    auth_provider_id: string | null;
    avatar_url: string | null;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface FavouriteStreamer {
    id: number;
    user_id: number;
    streamer_id: number;
    streamer_name: string;
    created_at: string;
    updated_at: string;
    subscription_status: 'subscribed' | 'unsubscribed' | 'webhook_callback_verification_pending' | 'enabled';
    subscriptions: Array<Subscription>;
}

export interface Subscription {
    id: number;
    favourite_streamer_id: number;
    type: 'stream.online' | 'stream.offline' | 'channel.update';
    status: 'subscribed' | 'unsubscribed' | 'webhook_callback_verification_pending' | 'enabled';
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
