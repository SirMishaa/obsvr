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
    /** Todo: Remove me if possible, needed for now because of StreamHistoryDialog, otherwise it does not allow to mutate props.events */
    streamerEvents: Array<TwitchEvent>;
};

export interface User {
    id: number;
    name: string;
    email: string;
    auth_provider: 'twitch' | null;
    auth_provider_id: string | null;
    avatar_url: string | null;
    /** @deprecated Use avatar_url instead */
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

export interface TwitchEvent {
    id: number;
    event_id: string | null;
    event_type: 'stream.online' | 'stream.offline' | 'channel.update';
    streamer_id: string;
    streamer_name: string;
    payload: {
        id?: string;
        broadcaster_user_id?: string;
        broadcaster_user_login?: string;
        broadcaster_user_name?: string;
        type?: string;
        started_at?: string;
        title?: string;
        language?: string;
        category_id?: string;
        category_name?: string;
    };
    occurred_at: string;
    received_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
