<?php
$demoFormMessage = '';
$demoFormSuccess = false;
$newsletterMessage = '';
$newsletterSuccess = false;

$to = 'vikneshb@zoho.com';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit-demo'])) {
    $firstName = trim($_POST['first-name'] ?? '');
    $lastName = trim($_POST['last-name'] ?? '');
    $email = trim($_POST['work-email'] ?? '');
    $businessType = trim($_POST['business-type'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $subject = 'New B2B Collaboration Demo Request';
    $message = "First Name: {$firstName}\n" .
        "Last Name: {$lastName}\n" .
        "Corporate Email: {$email}\n" .
        "Business Type: {$businessType}\n" .
        "Network Scope: {$notes}\n";

    $headers = "From: no-reply@loganx.local\r\n" .
        "Reply-To: {$email}\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n";

    $demoFormSuccess = mail($to, $subject, $message, $headers);
    $demoFormMessage = $demoFormSuccess
        ? 'Thank you! Your B2B collaboration demo request has been sent successfully.'
        : 'There was a problem sending your message. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit-newsletter'])) {
    $newsletterEmail = trim($_POST['newsletter-email'] ?? '');

    $newsletterSubject = 'Retail Insights Newsletter Subscription';
    $newsletterBody = "Newsletter subscription request:\nEmail: {$newsletterEmail}\n";

    $newsletterHeaders = "From: no-reply@loganx.local\r\n" .
        "Reply-To: {$newsletterEmail}\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n";

    $newsletterSuccess = mail($to, $newsletterSubject, $newsletterBody, $newsletterHeaders);
    $newsletterMessage = $newsletterSuccess
        ? 'Thank you! Your newsletter subscription request has been sent successfully.'
        : 'There was a problem sending your newsletter request. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOGANX | B2B Wholesale & Retail Supermarket Collaboration Platform</title>
    <meta name="description" content="Enterprise B2B retail technology connecting supermarket chains and wholesale suppliers with real-time inventory sync, automated purchasing, and AI demand forecasting.">
    <meta name="keywords" content="B2B supermarket software, wholesale LOGANy platform, retail inventory sync, supply chain technology, supermarket collaboration">
    
    <!-- Schema.org Structured Data for B2B Enterprise Software -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "LOGANX B2B Platform",
      "operatingSystem": "Web, iOS, Android",
      "applicationCategory": "BusinessApplication",
      "offers": {
        "@type": "AggregateOffer",
        "priceCurrency": "USD",
        "lowPrice": "299.00",
        "highPrice": "1499.00"
      },
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.95",
        "reviewCount": "850"
      }
    }
    </script>

    <!-- Tailwind CSS & Professional Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons, Alpine.js, & Chart.js -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter', 'sans-serif'],
              heading: ['Plus Jakarta Sans', 'sans-serif'],
            },
            colors: {
              brand: {
                primary: '#2563EB',
                secondary: '#4F46E5',
                accent: '#06B6D4',
                success: '#10B981',
                dark: '#0F172A',
                light: '#F8FAFC',
                text: '#0F172A',
                muted: '#475569'
              }
            },
            borderRadius: {
              'xl2': '16px',
            }
          }
        }
      }
    </script>

    <style>
      .glassmorphism {
        background: rgba(255, 255, 255, 0.80);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.4);
      }
      .dark .glassmorphism {
        background: rgba(15, 23, 42, 0.80);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.08);
      }
      .gradient-hero {
        background: radial-gradient(circle at 50% 0%, rgba(37, 99, 235, 0.12) 0%, rgba(79, 70, 229, 0.04) 50%, rgba(255, 255, 255, 0) 100%);
      }
      .dark .gradient-hero {
        background: radial-gradient(circle at 50% 0%, rgba(37, 99, 235, 0.22) 0%, rgba(15, 23, 42, 0) 70%);
      }
    </style>
</head>
<body class="bg-brand-light dark:bg-brand-dark text-brand-text dark:text-slate-100 font-sans antialiased transition-colors duration-300" x-data="{ darkMode: false }" :class="{ 'dark': darkMode }">

    <!-- Accessible Navigation -->
    <header class="sticky top-0 z-50 glassmorphism border-b border-slate-200/60 dark:border-slate-800/60 transition-all">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between" aria-label="Main Navigation">
            
            <!-- Logo -->
            <a href="#" class="flex items-center gap-2.5 focus:outline-none focus:ring-2 focus:ring-brand-primary rounded-xl p-1">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-primary via-brand-secondary to-brand-accent flex items-center justify-center text-white shadow-lg shadow-brand-primary/25">
                    <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-heading text-xl font-bold tracking-tight text-slate-900 dark:text-white leading-none">LOGAN<span class="text-brand-primary">X</span></span>
                    <span class="text-[10px] font-semibold text-brand-secondary dark:text-brand-accent tracking-wider uppercase mt-0.5">Enterprise Retail B2B</span>
                </div>
            </a>

            <!-- Desktop Links -->
            <div class="hidden lg:flex items-center gap-8 text-sm font-medium text-brand-muted dark:text-slate-300">
                <a href="#solutions" class="hover:text-brand-primary dark:hover:text-white transition">Solutions</a>
                <a href="#features" class="hover:text-brand-primary dark:hover:text-white transition">Platform Features</a>
                <a href="#analytics" class="hover:text-brand-primary dark:hover:text-white transition">Retail Analytics</a>
                <a href="#pricing" class="hover:text-brand-primary dark:hover:text-white transition">Pricing</a>
                <a href="#security" class="hover:text-brand-primary dark:hover:text-white transition">Enterprise Compliance</a>
            </div>

            <!-- Actions & Dark Mode Toggle -->
            <div class="hidden lg:flex items-center gap-4">
                <button @click="darkMode = !darkMode" class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 text-brand-muted dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition focus:outline-none focus:ring-2 focus:ring-brand-primary" aria-label="Toggle Dark Mode">
                    <i data-lucide="sun" x-show="darkMode" class="w-4 h-4 text-amber-400"></i>
                    <i data-lucide="moon" x-show="!darkMode" class="w-4 h-4 text-slate-600"></i>
                </button>
                <a href="#contact" class="text-sm font-semibold text-slate-700 dark:text-slate-200 hover:text-brand-primary transition px-3 py-2">Vendor Login</a>
                <a href="#contact" class="text-sm font-semibold text-white bg-brand-primary hover:bg-brand-secondary transition px-5 py-2.5 rounded-xl shadow-md shadow-brand-primary/20 hover:scale-[1.02] active:scale-[0.98]">
                    Request Enterprise Demo
                </a>
            </div>

            <!-- Mobile Menu Toggle -->
            <div class="flex lg:hidden items-center gap-2" x-data="{ open: false }">
                <button @click="darkMode = !darkMode" class="p-2 rounded-lg text-slate-600 dark:text-slate-300">
                    <i data-lucide="moon" class="w-5 h-5" x-show="!darkMode"></i>
                    <i data-lucide="sun" class="w-5 h-5" x-show="darkMode"></i>
                </button>
                <button @click="open = !open" class="p-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 focus:outline-none" aria-label="Toggle Navigation Menu">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="relative pt-12 pb-20 lg:pt-24 lg:pb-32 gradient-hero overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto space-y-6">
                
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-brand-primary/20 bg-brand-primary/10 text-brand-primary dark:text-brand-accent text-xs font-semibold uppercase tracking-wider">
                    <i data-lucide="truck" class="w-3.5 h-3.5"></i> Wholesale & Supermarket B2B Collaboration Engine
                </div>

                <h1 class="font-heading text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 dark:text-white leading-[1.15] tracking-tight">
                    Connecting <span class="bg-gradient-to-r from-brand-primary via-brand-secondary to-brand-accent bg-clip-text text-transparent">Supermarkets & Wholesalers</span> in Real-Time
                </h1>

                <p class="text-base sm:text-lg text-brand-muted dark:text-slate-400 leading-relaxed">
                    Automate procurement, synchronize multi-branch retail inventory, and eliminate stockouts with an enterprise-grade collaborative tech stack.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                    <a href="#contact" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-brand-primary text-white font-semibold shadow-lg shadow-brand-primary/25 hover:bg-brand-secondary hover:scale-[1.02] transition flex items-center justify-center gap-2">
                        Get Started for Enterprise <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                    <a href="#analytics" class="w-full sm:w-auto px-8 py-4 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition flex items-center justify-center gap-2">
                        <i data-lucide="bar-chart-2" class="w-4 h-4 text-brand-primary"></i> Explore Live Analytics
                    </a>
                </div>

                <div class="pt-6 flex flex-wrap items-center justify-center gap-6 text-xs font-medium text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-brand-success"></i> ISO 27001 & SOC 2 Certified</span>
                    <span class="flex items-center gap-1.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-brand-success"></i> Real-time ERP / POS Integration</span>
                    <span class="flex items-center gap-1.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-brand-success"></i> 99.99% Uptime Guarantee</span>
                </div>
            </div>

            <!-- Hero Interactive Dashboard Mockup -->
            <div class="mt-12 relative mx-auto max-w-5xl rounded-2xl p-2 bg-gradient-to-b from-slate-200/80 to-slate-200/20 dark:from-slate-700/50 dark:to-slate-900/10 shadow-2xl border border-slate-200/60 dark:border-slate-800/60">
                <div class="rounded-xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                    
                    <!-- Dashboard Header -->
                    <div class="h-11 bg-slate-100 dark:bg-slate-800 px-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-rose-500"></div>
                            <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                            <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                            <span class="text-xs text-slate-400 font-mono ml-3 hidden sm:inline">b2b-portal.LOGANX.com/retail-network</span>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-brand-success animate-pulse"></i> 1,420 Stores Connected</span>
                        </div>
                    </div>

                    <!-- Dashboard Body -->
                    <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6 bg-slate-50/50 dark:bg-slate-900/50">
                        
                        <!-- Left Status Card -->
                        <div class="lg:col-span-2 space-y-4">
                            <div class="glassmorphism p-5 rounded-xl border border-slate-200 dark:border-slate-800">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h2 class="font-heading font-bold text-sm text-slate-900 dark:text-white">Active Wholesale Purchase Orders</h2>
                                        <p class="text-xs text-slate-500">Automated Reordering Engine</p>
                                    </div>
                                    <span class="px-2.5 py-1 text-xs rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 font-semibold">Live Sync</span>
                                </div>
                                <div class="space-y-3">
                                    <div class="p-3 bg-white dark:bg-slate-800 rounded-lg flex items-center justify-between border border-slate-200/80 dark:border-slate-700/80 text-xs">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-brand-primary/10 text-brand-primary rounded-lg"><i data-lucide="package" class="w-4 h-4"></i></div>
                                            <div>
                                                <div class="font-semibold text-slate-900 dark:text-white">PO-9842: Organic Produce Order</div>
                                                <div class="text-slate-500">Metro Supermarket Chain (42 Branches)</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-slate-900 dark:text-white">$142,500.00</div>
                                            <div class="text-emerald-500 text-[10px]">Dispatch Confirmed</div>
                                        </div>
                                    </div>

                                    <div class="p-3 bg-white dark:bg-slate-800 rounded-lg flex items-center justify-between border border-slate-200/80 dark:border-slate-700/80 text-xs">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-brand-secondary/10 text-brand-secondary rounded-lg"><i data-lucide="refresh-cw" class="w-4 h-4"></i></div>
                                            <div>
                                                <div class="font-semibold text-slate-900 dark:text-white">PO-9843: Cold Storage Dairy Sync</div>
                                                <div class="text-slate-500">Apex Regional Wholesale Dist.</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-slate-900 dark:text-white">$88,200.00</div>
                                            <div class="text-brand-accent text-[10px]">In Transit</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Stats Card -->
                        <div class="glassmorphism p-5 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-col justify-between">
                            <div>
                                <span class="text-xs font-semibold text-brand-muted dark:text-slate-400">Collaborative Efficiency</span>
                                <div class="text-3xl font-extrabold font-heading text-slate-900 dark:text-white mt-2">+34.8%</div>
                                <p class="text-xs text-slate-500 mt-1">Reduction in supermarket out-of-stock incidents through automated AI dispatch.</p>
                            </div>
                            <div class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800">
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-slate-500">Fulfillment Speed</span>
                                    <span class="font-bold text-brand-primary">99.4%</span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                                    <div class="bg-brand-primary h-full w-[99.4%]"></div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Trusted Partners Banner -->
    <section class="py-10 bg-white dark:bg-slate-900 border-y border-slate-200/60 dark:border-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-6">Trusted by Leading FMCG Brands, Supermarket Chains & Wholesale Distributors</p>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-8 items-center opacity-70 dark:opacity-50 font-heading font-extrabold text-lg tracking-tight">
                <span>GOVINDARAJAN STORE</span>
                <span>GOVIND MALLIGAI</span>
                <span>GOVIND SUPERMARKET</span>
                <span>G3 ONLINE</span>
                
            </div>
        </div>
    </section>

    <!-- B2B Solutions Section -->
    <section id="solutions" class="py-20 lg:py-28 bg-brand-light dark:bg-brand-dark">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-brand-primary dark:text-brand-accent font-semibold text-xs uppercase tracking-widest">Designed for Wholesale & Retail Scale</span>
                <h2 class="font-heading text-3xl sm:text-4xl font-bold mt-2 text-slate-900 dark:text-white">Unified Supermarket Supply Chain Collaboration</h2>
                <p class="text-brand-muted dark:text-slate-400 mt-3 text-sm">Eliminate supply chain latency between FMCG suppliers, regional wholesalers, and retail store networks.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                
                <!-- Card 1 -->
                <div class="p-8 rounded-2xl glassmorphism border border-slate-200/80 dark:border-slate-800 hover:border-brand-primary/50 transition-all duration-300 hover:-translate-y-1 shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-brand-primary/10 text-brand-primary flex items-center justify-center mb-6">
                        <i data-lucide="refresh-cw" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-heading text-xl font-bold mb-3 text-slate-900 dark:text-white">Automated EDI & POS Sync</h3>
                    <p class="text-brand-muted dark:text-slate-400 text-sm leading-relaxed">
                        Connect store Point-of-Sale data directly to wholesale ERPs for real-time automatic replenishment when shelf thresholds drop.
                    </p>
                </div>

                <!-- Card 2 -->
                <div class="p-8 rounded-2xl glassmorphism border border-slate-200/80 dark:border-slate-800 hover:border-brand-primary/50 transition-all duration-300 hover:-translate-y-1 shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-brand-secondary/10 text-brand-secondary flex items-center justify-center mb-6">
                        <i data-lucide="trending-up" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-heading text-xl font-bold mb-3 text-slate-900 dark:text-white">AI Demand Forecasting</h3>
                    <p class="text-brand-muted dark:text-slate-400 text-sm leading-relaxed">
                        Predict seasonal LOGANy spikes, weather disruptions, and promotional surges to optimize wholesale safety stock levels.
                    </p>
                </div>

                <!-- Card 3 -->
                <div class="p-8 rounded-2xl glassmorphism border border-slate-200/80 dark:border-slate-800 hover:border-brand-primary/50 transition-all duration-300 hover:-translate-y-1 shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-brand-accent/10 text-brand-accent flex items-center justify-center mb-6">
                        <i data-lucide="layers" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-heading text-xl font-bold mb-3 text-slate-900 dark:text-white">Multi-Store Fleet & Route Routing</h3>
                    <p class="text-brand-muted dark:text-slate-400 text-sm leading-relaxed">
                        Collaborate with logistics teams to batch wholesale deliveries across regional retail branches, cutting transit costs by up to 28%.
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- Interactive Analytics Dashboard Section -->
    <section id="analytics" class="py-20 bg-white dark:bg-slate-900/60 border-y border-slate-200/60 dark:border-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 items-center">
                
                <div>
                    <span class="text-brand-primary dark:text-brand-accent font-semibold text-xs uppercase tracking-widest">Real-Time Operations</span>
                    <h2 class="font-heading text-3xl font-bold mt-2 text-slate-900 dark:text-white">Data-Driven Retail & Wholesale Insights</h2>
                    <p class="text-brand-muted dark:text-slate-400 text-sm mt-4 leading-relaxed">
                        Gain complete multi-tiered visibility over order fulfillment rates, wholesale price fluctuations, and perishable goods turnover velocity.
                    </p>

                    <div class="mt-8 space-y-4">
                        <div class="flex items-center gap-3 text-sm font-semibold text-slate-800 dark:text-slate-200">
                            <i data-lucide="check-circle" class="w-5 h-5 text-brand-success"></i> Instant SKU-Level Profit Margins
                        </div>
                        <div class="flex items-center gap-3 text-sm font-semibold text-slate-800 dark:text-slate-200">
                            <i data-lucide="check-circle" class="w-5 h-5 text-brand-success"></i> Automated Credit Limit Approvals
                        </div>
                        <div class="flex items-center gap-3 text-sm font-semibold text-slate-800 dark:text-slate-200">
                            <i data-lucide="check-circle" class="w-5 h-5 text-brand-success"></i> Cold-Chain Integrity Tracking
                        </div>
                    </div>
                </div>

                <!-- Interactive Chart Container -->
                <div class="lg:col-span-2 glassmorphism p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-lg">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="font-heading font-bold text-sm text-slate-900 dark:text-white">Wholesale vs Retail Inventory Turnover Velocity</h3>
                            <p class="text-xs text-slate-500">Live Weekly Comparison</p>
                        </div>
                        <span class="text-xs font-mono bg-brand-primary/10 text-brand-primary px-3 py-1 rounded-full font-semibold">Updated Just Now</span>
                    </div>
                    <div class="relative h-64 w-full">
                        <canvas id="retailChart"></canvas>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Security & Security Certifications -->
    <section id="security" class="py-20 bg-brand-dark text-white relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-brand-accent font-semibold text-xs uppercase tracking-widest">Enterprise Trust & Security</span>
                <h2 class="font-heading text-3xl sm:text-4xl font-bold mt-2">Bank-Grade Retail Data Security</h2>
                <p class="text-slate-400 mt-3 text-sm">Protected by end-to-end encryption, custom retention controls, and enterprise certifications.</p>
            </div>

            <!-- Security Badges Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="p-6 rounded-xl bg-slate-800/60 border border-slate-700/60 text-center flex flex-col items-center justify-center gap-2 hover:bg-slate-800 transition">
                    <i data-lucide="award" class="w-7 h-7 text-brand-accent"></i>
                    <span class="font-heading font-bold text-sm">ISO 27001</span>
                    <span class="text-[10px] text-slate-400">Information Security</span>
                </div>
                <div class="p-6 rounded-xl bg-slate-800/60 border border-slate-700/60 text-center flex flex-col items-center justify-center gap-2 hover:bg-slate-800 transition">
                    <i data-lucide="shield-check" class="w-7 h-7 text-brand-accent"></i>
                    <span class="font-heading font-bold text-sm">SOC 2 Type II</span>
                    <span class="text-[10px] text-slate-400">Audited Compliance</span>
                </div>
                <div class="p-6 rounded-xl bg-slate-800/60 border border-slate-700/60 text-center flex flex-col items-center justify-center gap-2 hover:bg-slate-800 transition">
                    <i data-lucide="lock" class="w-7 h-7 text-brand-accent"></i>
                    <span class="font-heading font-bold text-sm">GDPR Ready</span>
                    <span class="text-[10px] text-slate-400">Data Privacy Compliance</span>
                </div>
                <div class="p-6 rounded-xl bg-slate-800/60 border border-slate-700/60 text-center flex flex-col items-center justify-center gap-2 hover:bg-slate-800 transition">
                    <i data-lucide="file-text" class="w-7 h-7 text-brand-accent"></i>
                    <span class="font-heading font-bold text-sm">HIPAA Standard</span>
                    <span class="text-[10px] text-slate-400">Health & Pharma Logistics</span>
                </div>
                <div class="p-6 rounded-xl bg-slate-800/60 border border-slate-700/60 text-center flex flex-col items-center justify-center gap-2 hover:bg-slate-800 transition">
                    <i data-lucide="key" class="w-7 h-7 text-brand-accent"></i>
                    <span class="font-heading font-bold text-sm">TLS 1.3 / SSL</span>
                    <span class="text-[10px] text-slate-400">Encrypted In-Transit</span>
                </div>
                <div class="p-6 rounded-xl bg-slate-800/60 border border-slate-700/60 text-center flex flex-col items-center justify-center gap-2 hover:bg-slate-800 transition">
                    <i data-lucide="shield-alert" class="w-7 h-7 text-brand-accent"></i>
                    <span class="font-heading font-bold text-sm">E2E Encryption</span>
                    <span class="text-[10px] text-slate-400">Zero-Trust Protocol</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-20 lg:py-28 bg-brand-light dark:bg-brand-dark" x-data="{ annual: true }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="text-brand-primary dark:text-brand-accent font-semibold text-xs uppercase tracking-widest">Flexible B2B Pricing</span>
                <h2 class="font-heading text-3xl sm:text-4xl font-bold mt-2 text-slate-900 dark:text-white">Scaled for Wholesale Operations & Retail Chains</h2>
                
                <!-- Toggle -->
                <div class="flex items-center justify-center gap-4 mt-8">
                    <span class="text-sm font-medium" :class="!annual ? 'text-slate-900 dark:text-white font-bold' : 'text-slate-500'">Monthly Billing</span>
                    <button @click="annual = !annual" class="relative w-14 h-8 bg-slate-300 dark:bg-slate-700 rounded-full p-1 transition focus:outline-none focus:ring-2 focus:ring-brand-primary" aria-label="Toggle Annual Billing">
                        <div class="w-6 h-6 bg-brand-primary rounded-full transition-transform" :class="annual ? 'translate-x-6' : 'translate-x-0'"></div>
                    </button>
                    <span class="text-sm font-medium" :class="annual ? 'text-slate-900 dark:text-white font-bold' : 'text-slate-500'">
                        Annual Billing <span class="text-xs text-brand-success bg-emerald-100 dark:bg-emerald-950 px-2.5 py-0.5 rounded-full font-semibold">Save 20%</span>
                    </span>
                </div>
            </div>

             <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- CARD 1: Fruit Shop POS -->
                <div class="glass-card rounded-2xl p-6 flex flex-col justify-between hover:shadow-xl hover:-translate-y-1 transition duration-300 border border-slate-200 dark:border-slate-800">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-4">
                            <i data-lucide="apple" class="w-6 h-6"></i>
                        </div>
                        <h3 class="font-heading font-bold text-lg text-slate-900 dark:text-white">Fruit Shop POS</h3>
                        <p class="text-slate-500 text-xs mt-1 min-h-[32px]">Tailored for fresh fruit outlets, juice bars, and fruit wholesalers.</p>
                        
                        <div class="mt-4 mb-6">
                            <span class="text-3xl font-extrabold font-heading text-slate-900 dark:text-white" x-text="annual ? '$29' : '$35'">$29</span>
                            <span class="text-slate-500 text-xs">/ outlet / mo</span>
                        </div>

                        <div class="border-t border-slate-200 dark:border-slate-800 pt-4 mb-6">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Key Features</span>
                            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300">
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Weighing Scale Machine Sync</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Perishable Spoilage Tracker</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Batch & Freshness Alerts</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Retail & Wholesale Rates</li>
                            </ul>
                        </div>
                    </div>
                    <a href="#contact" class="w-full py-2.5 text-center text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-brand-primary hover:text-white dark:hover:bg-brand-primary hover:border-brand-primary transition">Select Plan</a>
                </div>

                <!-- CARD 2: Vegetable Shop POS (POPULAR) -->
                <div class="glass-card rounded-2xl p-6 flex flex-col justify-between hover:shadow-xl hover:-translate-y-1 transition duration-300 border-2 border-brand-primary relative bg-white dark:bg-slate-900">
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand-primary text-white text-[10px] font-bold uppercase tracking-wider px-3 py-0.5 rounded-full shadow-md">Most Popular</span>
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-4">
                            <i data-lucide="carrot" class="w-6 h-6"></i>
                        </div>
                        <h3 class="font-heading font-bold text-lg text-slate-900 dark:text-white">Vegetable Shop POS</h3>
                        <p class="text-slate-500 text-xs mt-1 min-h-[32px]">Built for high-volume veggie marts & supermarket green sections.</p>
                        
                        <div class="mt-4 mb-6">
                            <span class="text-3xl font-extrabold font-heading text-slate-900 dark:text-white" x-text="annual ? '$39' : '$49'">$39</span>
                            <span class="text-slate-500 text-xs">/ outlet / mo</span>
                        </div>

                        <div class="border-t border-slate-200 dark:border-slate-800 pt-4 mb-6">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Key Features</span>
                            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300">
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Fast POS Barcode & Weight Printing</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Daily Price Modification Engine</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Multi-Crate Wholesale Billing</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Stock & Wastage Analytics</li>
                            </ul>
                        </div>
                    </div>
                    <a href="#contact" class="w-full py-2.5 text-center text-xs font-semibold rounded-xl bg-brand-primary text-white hover:bg-brand-secondary transition shadow-md shadow-brand-primary/20">Start Free Trial</a>
                </div>

                <!-- CARD 3: Hospital Booking Software -->
                <div class="glass-card rounded-2xl p-6 flex flex-col justify-between hover:shadow-xl hover:-translate-y-1 transition duration-300 border border-slate-200 dark:border-slate-800">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 flex items-center justify-center mb-4">
                            <i data-lucide="stethoscope" class="w-6 h-6"></i>
                        </div>
                        <h3 class="font-heading font-bold text-lg text-slate-900 dark:text-white">Hospital Booking</h3>
                        <p class="text-slate-500 text-xs mt-1 min-h-[32px]">Appointment scheduling for clinics, diagnostic centers & hospitals.</p>
                        
                        <div class="mt-4 mb-6">
                            <span class="text-3xl font-extrabold font-heading text-slate-900 dark:text-white" x-text="annual ? '$119' : '$149'">$119</span>
                            <span class="text-slate-500 text-xs">/ facility / mo</span>
                        </div>

                        <div class="border-t border-slate-200 dark:border-slate-800 pt-4 mb-6">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Key Features</span>
                            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300">
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Doctor Slot & OPD Scheduling</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> WhatsApp & SMS Reminders</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Patient Queue Management Screen</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> EHR & Consultation Billing</li>
                            </ul>
                        </div>
                    </div>
                    <a href="#contact" class="w-full py-2.5 text-center text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-brand-primary hover:text-white dark:hover:bg-brand-primary hover:border-brand-primary transition">Select Plan</a>
                </div>

                <!-- CARD 4: Car Wash & Detailing POS -->
                <div class="glass-card rounded-2xl p-6 flex flex-col justify-between hover:shadow-xl hover:-translate-y-1 transition duration-300 border border-slate-200 dark:border-slate-800">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-4">
                            <i data-lucide="car" class="w-6 h-6"></i>
                        </div>
                        <h3 class="font-heading font-bold text-lg text-slate-900 dark:text-white">Car Wash & Auto</h3>
                        <p class="text-slate-500 text-xs mt-1 min-h-[32px]">Streamlined POS for car wash bays, detailing & auto service centers.</p>
                        
                        <div class="mt-4 mb-6">
                            <span class="text-3xl font-extrabold font-heading text-slate-900 dark:text-white" x-text="annual ? '$49' : '$59'">$49</span>
                            <span class="text-slate-500 text-xs">/ center / mo</span>
                        </div>

                        <div class="border-t border-slate-200 dark:border-slate-800 pt-4 mb-6">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Key Features</span>
                            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300">
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Service Package & Bay Tracking</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Vehicle Queue Status Updates</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Membership & Prepaid Cards</li>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-primary shrink-0"></i> Commission Tracker for Staff</li>
                            </ul>
                        </div>
                    </div>
                    <a href="#contact" class="w-full py-2.5 text-center text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-brand-primary hover:text-white dark:hover:bg-brand-primary hover:border-brand-primary transition">Select Plan</a>
                </div>

            </div>
        </div>
    </section>

    <!-- Accessible Demo Form Section -->
    <section id="contact" class="py-20 bg-slate-100 dark:bg-slate-900/50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="glassmorphism rounded-2xl p-8 sm:p-12 border border-slate-200 dark:border-slate-800 shadow-xl">
                <div class="text-center mb-8">
                    <h2 class="font-heading text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">Request a B2B Collaboration Demo</h2>
                    <p class="text-brand-muted dark:text-slate-400 text-sm mt-2">Connect with our supermarket technology architects to digitize your wholesale chain.</p>
                </div>

                <?php if ($demoFormMessage): ?>
                    <div class="mb-6 rounded-xl border px-4 py-3 text-sm <?php echo $demoFormSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'; ?>">
                        <?php echo htmlspecialchars($demoFormMessage); ?>
                    </div>
                <?php endif; ?>

                <form id="demoForm" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="first-name" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">First Name</label>
                            <input type="text" id="first-name" name="first-name" required class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-primary focus:outline-none transition">
                        </div>
                        <div>
                            <label for="last-name" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Last Name</label>
                            <input type="text" id="last-name" name="last-name" required class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-primary focus:outline-none transition">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="work-email" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Corporate Email</label>
                            <input type="email" id="work-email" name="work-email" required placeholder="name@supermarket.com" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-primary focus:outline-none transition">
                        </div>
                        <div>
                            <label for="business-type" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Business Type</label>
                            <select id="business-type" name="business-type" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-primary focus:outline-none transition">
                                <option>Supermarket Chain Retailer</option>
                                <option>Wholesale Food Distributor</option>
                                <option>FMCG Manufacturer / Supplier</option>
                                <option>3PL / Cold-Chain Logistics Provider</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Number of Stores / Network Scope</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Tell us about your current store count or ERP system..." class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-primary focus:outline-none transition"></textarea>
                    </div>

                    <button type="submit" name="submit-demo" class="w-full py-4 rounded-xl bg-brand-primary hover:bg-brand-secondary text-white font-semibold shadow-lg shadow-brand-primary/20 transition hover:scale-[1.01] active:scale-[0.99]">
                        Submit Demo Request
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Comprehensive Footer -->
    <footer class="bg-brand-dark text-slate-400 text-sm py-16 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-8 mb-12">
                
                <!-- Logo / Info Column -->
                <div class="col-span-2">
                    <div class="flex items-center gap-2 text-white font-bold text-xl mb-4">
                        <div class="w-8 h-8 rounded-lg bg-brand-primary flex items-center justify-center">
                            <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        </div>
                        LOGANX
                    </div>
                    <p class="text-xs text-slate-400 mb-6 leading-relaxed max-w-sm">
                        The modern standard for wholesale supermarket collaboration, multi-store replenishment, and AI supply-chain automation.
                    </p>
                    
                    <!-- Newsletter -->
                    <div class="space-y-2">
                        <span class="block text-xs font-semibold text-white uppercase tracking-wider">Retail Insights Newsletter</span>
                        <?php if ($newsletterMessage): ?>
                            <div class="rounded-lg border px-3 py-2 text-xs <?php echo $newsletterSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'; ?>">
                                <?php echo htmlspecialchars($newsletterMessage); ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="flex gap-2">
                            <input type="email" name="newsletter-email" required placeholder="Email address" class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs focus:outline-none focus:ring-1 focus:ring-brand-primary">
                            <button type="submit" name="submit-newsletter" class="px-3 py-2 bg-brand-primary hover:bg-brand-secondary text-white text-xs rounded-lg font-semibold transition">Subscribe</button>
                        </form>
                    </div>
                </div>

                <!-- Nav Columns -->
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-white mb-4">Products</h3>
                    <ul class="space-y-2 text-xs">
                        <li><a href="#" class="hover:text-white transition">Retail Store POS Sync</a></li>
                        <li><a href="#" class="hover:text-white transition">Wholesale Order Engine</a></li>
                        <li><a href="#" class="hover:text-white transition">AI Demand Forecast</a></li>
                        <li><a href="#" class="hover:text-white transition">EDI Connect API</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-white mb-4">Solutions</h3>
                    <ul class="space-y-2 text-xs">
                        <li><a href="#" class="hover:text-white transition">Supermarket Chains</a></li>
                        <li><a href="#" class="hover:text-white transition">FMCG Wholesalers</a></li>
                        <li><a href="#" class="hover:text-white transition">Fresh Produce Supply</a></li>
                        <li><a href="#" class="hover:text-white transition">Cold-Chain Logistics</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-white mb-4">Resources</h3>
                    <ul class="space-y-2 text-xs">
                        <li><a href="#" class="hover:text-white transition">API Documentation</a></li>
                        <li><a href="../onesite/Stag.html" class="hover:text-white transition">ERP Connectors</a></li>
                        <li><a href=" https://gym-logan-io.vercel.app/" class="hover:text-white transition">Security Standards</a></li>
                        <li><a href=" https://angularpos.vercel.app/#/salse"class=""" class="hover:text-white transition">System Status</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-white mb-4">Company</h3>
                    <ul class="space-y-2 text-xs">
                        <li><a href="#" class="hover:text-white transition">About Us</a></li>
                        <li><a href="#" class="hover:text-white transition">Careers</a></li>
                        <li><a href="#" class="hover:text-white transition">Contact Us</a></li>
                        <li><a href="#" class="hover:text-white transition">Legal & Privacy</a></li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Row / Social Links -->
            <div class="pt-8 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
                <p>&copy; 2026 LOGANX Enterprise Technologies Inc. All rights reserved.</p>
                <div class="flex gap-6">
                    <a href="#" class="hover:text-white transition">Privacy Policy</a>
                    <a href="#" class="hover:text-white transition">Terms of Service</a>
                    <a href="#" class="hover:text-white transition">Cookie Settings</a>
                </div>
                <div class="flex gap-4">
                    <a href="#" class="hover:text-white transition" aria-label="Twitter"><i data-lucide="twitter" class="w-4 h-4"></i></a>
                    <a href="#" class="hover:text-white transition" aria-label="LinkedIn"><i data-lucide="linkedin" class="w-4 h-4"></i></a>
                    <a href="#" class="hover:text-white transition" aria-label="GitHub"><i data-lucide="github" class="w-4 h-4"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Lucide Icons & Chart.js Initialization -->
    <script>
        // Render Icons
        lucide.createIcons();

        // Chart Initialization
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('retailChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [
                        {
                            label: 'Wholesale Outflow (Pallets)',
                            data: [120, 190, 300, 250, 420, 550, 480],
                            borderColor: '#2563EB',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Supermarket POS Sales Volume',
                            data: [100, 160, 230, 210, 380, 510, 430],
                            borderColor: '#06B6D4',
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#94A3B8',
                                font: { family: 'Inter', size: 11 }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(148, 163, 184, 0.1)' },
                            ticks: { color: '#94A3B8', font: { family: 'Inter', size: 10 } }
                        },
                        y: {
                            grid: { color: 'rgba(148, 163, 184, 0.1)' },
                            ticks: { color: '#94A3B8', font: { family: 'Inter', size: 10 } }
                        }
                    }
                }
            });
        });
    </script>
    <script>
  const form = document.getElementById('demoForm');
  alert('Thank you! A LOGANX B2B specialist will contact you shortly.',form);
  const statusMsg = document.getElementById('statusMsg');

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    statusMsg.style.color = '#333';
    statusMsg.innerText = 'Sending...';

    const formData = new FormData(form);
    
    try {
         alert('Thank you! A LOGANX B2B specialist will contact you shortly.',formData);
      // You can replace this endpoint with your Node.js/Python backend URL or service like Formspree
      const response = await fetch('https://api.web3forms.com/submit', {
        method: 'POST',
        body: formData
      });

      if (response.ok) {
        statusMsg.style.color = 'green';
        statusMsg.innerText = 'Demo request submitted successfully! We will contact you shortly.';
        form.reset();
      } else {
        throw new Error('Submission failed');
      }
    } catch (error) {
      statusMsg.style.color = 'red';
      statusMsg.innerText = 'An error occurred. Please try again later.';
    }
  });
</script>
</body>
</html>