<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { home, login, register, twitch } from '@/routes';
import { redirect } from '@/routes/socialite';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Bell, Eye, Github, History, LogIn, Moon, Zap } from 'lucide-vue-next';

const page = usePage();
</script>

<template>
    <Head title="Obsvr - Never Miss Your Favorite Twitch Streams">
        <meta
            name="description"
            content="Get real-time push notifications when your favorite Twitch streamers go live. Track stream history, manage subscriptions, and never miss a moment."
        />
    </Head>

    <div class="min-h-screen bg-background">
        <!-- Navigation Bar -->
        <nav class="border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <!-- Logo -->
                    <Link :href="home()" class="flex items-center gap-2">
                        <AppLogoIcon class="size-8 fill-current text-foreground" />
                        <span class="text-lg font-semibold">Obsvr</span>
                    </Link>

                    <!-- Auth Links -->
                    <div class="flex items-center gap-3">
                        <template v-if="page.props.auth.user">
                            <Button as-child variant="default" size="sm">
                                <Link :href="twitch()">Dashboard</Link>
                            </Button>
                        </template>
                        <template v-else>
                            <Button as-child variant="ghost" size="sm">
                                <Link :href="login()">Log in</Link>
                            </Button>
                            <Button as-child variant="default" size="sm" class="hidden sm:inline-flex">
                                <Link :href="register()">Sign up</Link>
                            </Button>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative overflow-hidden bg-background py-16 sm:py-24 lg:py-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <!-- Main Headline -->
                    <h1 class="text-4xl font-bold tracking-tight text-foreground sm:text-5xl lg:text-6xl">
                        Never Miss Your Favorite
                        <span
                            class="bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent dark:from-purple-400 dark:to-pink-400"
                        >
                            Twitch Streams
                        </span>
                    </h1>

                    <!-- Subheadline -->
                    <p class="mt-6 text-lg leading-8 text-muted-foreground sm:text-xl">
                        Get real-time push notifications when your favorite streamers go live. Track stream history, manage subscriptions, and never
                        miss a moment.
                    </p>

                    <!-- CTA Buttons -->
                    <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Button
                            as="a"
                            :href="redirect('twitch').url"
                            size="lg"
                            class="group inline-flex w-full items-center gap-2 bg-[#9146FF] text-white hover:bg-[#772CE8] sm:w-auto dark:bg-[#9146FF] dark:hover:bg-[#772CE8]"
                        >
                            <!-- Twitch Icon SVG -->
                            <svg class="size-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M4.64 0L1 6v17h7V24l2.75-2.75h4.75L23 14.5V0H4.64zM20 13.5L15 18H10.25L8 20.25V18H4V2h16v11.5z" />
                                <path d="M17 4h-2v6h2V4zm-4 0h-2v6h2V4z" />
                            </svg>
                            Sign in with Twitch
                            <ArrowRight class="size-4 transition-transform group-hover:translate-x-1" />
                        </Button>

                        <Button as="a" href="#features" variant="outline" size="lg" class="w-full sm:w-auto"> Learn More </Button>
                    </div>

                    <!-- Trust indicator -->
                    <p class="mt-6 text-sm text-muted-foreground">
                        <Bell class="inline size-4" />
                        Free • Secure • Open Source
                    </p>
                </div>
            </div>
        </section>

        <!-- Screenshots Showcase Section -->
        <section id="screenshots" class="bg-muted/50 py-16 sm:py-24 dark:bg-muted/20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">Beautiful, Modern Interface</h2>
                    <p class="mt-4 text-lg text-muted-foreground">Built with Vue 3 and Tailwind CSS for a seamless experience</p>
                </div>

                <!-- Screenshots Grid -->
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                    <!-- Screenshot 1: Dashboard -->
                    <Card class="overflow-hidden border-border/50 dark:border-border">
                        <div class="aspect-video overflow-hidden bg-muted">
                            <img
                                src="/README-cover.png"
                                alt="Obsvr Dashboard - Track your favorite Twitch streamers"
                                class="h-full w-full object-cover object-top transition-transform duration-300 hover:scale-105"
                                loading="lazy"
                            />
                        </div>
                        <CardHeader>
                            <CardTitle class="text-lg">Live Stream Dashboard</CardTitle>
                            <CardDescription> See all your favorite streamers in one place with real-time updates </CardDescription>
                        </CardHeader>
                    </Card>

                    <!-- Screenshot 2: Subscription Settings -->
                    <Card class="overflow-hidden border-border/50 dark:border-border">
                        <div class="aspect-video overflow-hidden bg-muted">
                            <img
                                src="/README-cover-subs-settings.png"
                                alt="Subscription Settings - Manage your streamer notifications"
                                class="h-full w-full object-cover object-top transition-transform duration-300 hover:scale-105"
                                loading="lazy"
                            />
                        </div>
                        <CardHeader>
                            <CardTitle class="text-lg">Subscription Management</CardTitle>
                            <CardDescription> Easily manage which streamers you want to track and get notifications for </CardDescription>
                        </CardHeader>
                    </Card>

                    <!-- Screenshot 3: History -->
                    <Card class="overflow-hidden border-border/50 sm:col-span-2 lg:col-span-1 dark:border-border">
                        <div class="aspect-video overflow-hidden bg-muted">
                            <img
                                src="/README-cover-subs-history.png"
                                alt="Stream History - Complete timeline of streamer activity"
                                class="h-full w-full object-cover object-top transition-transform duration-300 hover:scale-105"
                                loading="lazy"
                            />
                        </div>
                        <CardHeader>
                            <CardTitle class="text-lg">Complete Stream History</CardTitle>
                            <CardDescription> View a detailed timeline of when your favorite streamers went live </CardDescription>
                        </CardHeader>
                    </Card>

                    <!-- Screenshot 4: Stream Schedule -->
                    <Card class="overflow-hidden border-border/50 sm:col-span-2 lg:col-span-1 dark:border-border">
                        <div class="aspect-video overflow-hidden bg-muted">
                            <img
                                src="/README-cover-stream-schedule.png"
                                alt="Stream Schedule - View upcoming streams from your favorite streamers"
                                class="h-full w-full object-cover object-top transition-transform duration-300 hover:scale-105"
                                loading="lazy"
                            />
                        </div>
                        <CardHeader>
                            <CardTitle class="text-lg">Stream Schedule</CardTitle>
                            <CardDescription> Plan ahead with a clear view of upcoming streams from your favorite Twitch channels </CardDescription>
                        </CardHeader>
                    </Card>
                </div>
            </div>
        </section>

        <!-- Features Grid Section -->
        <section id="features" class="bg-background py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">Everything You Need</h2>
                    <p class="mt-4 text-lg text-muted-foreground">Powerful features to keep you connected with your favorite Twitch content</p>
                </div>

                <!-- Features Grid -->
                <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Feature 1: Twitch Authentication -->
                    <div class="flex flex-col items-start gap-4">
                        <div class="flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20">
                            <LogIn class="size-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">Twitch Authentication</h3>
                            <p class="mt-2 text-muted-foreground">Securely sign in with your Twitch account using OAuth 2.0</p>
                        </div>
                    </div>

                    <!-- Feature 2: Real-time Events -->
                    <div class="flex flex-col items-start gap-4">
                        <div class="flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20">
                            <Zap class="size-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">Real-time Updates</h3>
                            <p class="mt-2 text-muted-foreground">Instant notifications powered by Twitch EventSub webhooks</p>
                        </div>
                    </div>

                    <!-- Feature 3: Push Notifications -->
                    <div class="flex flex-col items-start gap-4">
                        <div class="flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20">
                            <Bell class="size-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">Browser Notifications</h3>
                            <p class="mt-2 text-muted-foreground">Get push notifications when streamers go live, even when the app is closed</p>
                        </div>
                    </div>

                    <!-- Feature 4: Stream Monitoring -->
                    <div class="flex flex-col items-start gap-4">
                        <div class="flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20">
                            <Eye class="size-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">Stream Monitoring</h3>
                            <p class="mt-2 text-muted-foreground">Track your favorite streamers and their activities in real-time</p>
                        </div>
                    </div>

                    <!-- Feature 5: History & Timeline -->
                    <div class="flex flex-col items-start gap-4">
                        <div class="flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20">
                            <History class="size-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">Complete History</h3>
                            <p class="mt-2 text-muted-foreground">View a complete timeline of stream events and activity</p>
                        </div>
                    </div>

                    <!-- Feature 6: Dark Mode -->
                    <div class="flex flex-col items-start gap-4">
                        <div class="flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20">
                            <Moon class="size-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">Dark Mode Support</h3>
                            <p class="mt-2 text-muted-foreground">Full dark mode support for comfortable viewing at any time</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border bg-background">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <div class="flex items-center gap-2">
                        <AppLogoIcon class="size-6 fill-current text-foreground" />
                        <span class="text-sm text-muted-foreground"> Built with Laravel & Vue 3 </span>
                    </div>

                    <div class="flex gap-6">
                        <a
                            href="https://github.com/SirMishaa/obsvr"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <Github class="inline size-4" />
                            GitHub
                        </a>
                        <TextLink :href="login()" class="text-sm"> Sign In </TextLink>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
