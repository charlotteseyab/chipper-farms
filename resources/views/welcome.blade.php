<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chipper Farms | Smart Farm Management</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        :root{
            --green-900:#124534;
            --green-800:#155A43;
            --green-700:#1C6C51;
            --green-600:#218c6a;
            --green-100:#eaf6f1;
            --green-50:#f6fbf8;
            --text-main:#10221a;
            --text-soft:#5f6f68;
            --line:#dfeee7;
            --white:#ffffff;
            --shadow-sm:0 8px 24px rgba(16,34,26,.06);
            --shadow-md:0 18px 40px rgba(16,34,26,.10);
            --shadow-lg:0 28px 70px rgba(16,34,26,.14);
        }

        html{
            scroll-behavior:smooth;
        }

        body{
            font-family: Figtree, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(180deg, #f8fcfa 0%, #ffffff 100%);
            color: var(--text-main);
        }

        .container-wrap{
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .top-nav{
            position: sticky;
            top: 0;
            z-index: 50;
            border-bottom: 1px solid rgba(223,238,231,.85);
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .nav-link{
            position: relative;
            color: var(--green-700);
            font-weight: 600;
            transition: .25s ease;
            text-decoration: none;
        }

        .nav-link:hover{
            color: var(--green-900);
        }

        .nav-link::after{
            content:"";
            position:absolute;
            left:0;
            bottom:-8px;
            width:0;
            height:2px;
            border-radius:999px;
            background: linear-gradient(90deg, var(--green-700), var(--green-600));
            transition: width .25s ease;
        }

        .nav-link:hover::after{
            width:100%;
        }

        .btn-primary{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:.6rem;
            background: linear-gradient(135deg, var(--green-700), var(--green-600));
            color:#fff;
            border:none;
            border-radius: 14px;
            padding: 12px 20px;
            font-weight: 700;
            box-shadow: 0 14px 30px rgba(28,108,81,.22);
            transition: .28s ease;
            text-decoration:none;
        }

        .btn-primary:hover{
            transform: translateY(-2px);
            box-shadow: 0 20px 38px rgba(28,108,81,.28);
        }

        .btn-secondary{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:.6rem;
            background:#fff;
            color:var(--green-700);
            border:1px solid var(--line);
            border-radius: 14px;
            padding: 12px 20px;
            font-weight: 700;
            box-shadow: var(--shadow-sm);
            transition: .28s ease;
            text-decoration:none;
        }

        .btn-secondary:hover{
            transform: translateY(-2px);
            border-color: #c8e5da;
            box-shadow: var(--shadow-md);
        }

        .hero-bg{
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at top left, rgba(33,140,106,.10), transparent 34%),
                radial-gradient(circle at bottom right, rgba(28,108,81,.08), transparent 32%),
                linear-gradient(135deg, #f7fcf9 0%, #f2faf6 45%, #ffffff 100%);
        }

        .hero-bg::before{
            content:"";
            position:absolute;
            inset:0;
            background-image:
                linear-gradient(rgba(28,108,81,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(28,108,81,.025) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events:none;
        }

        .hero-clean{
            display:grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 40px;
            align-items: center;
        }

        .hero-badge{
            display:inline-flex;
            align-items:center;
            gap:.55rem;
            background: rgba(255,255,255,.88);
            border:1px solid #d8ebe2;
            border-radius: 999px;
            padding: 9px 16px;
            color: var(--green-700);
            font-size: .9rem;
            font-weight: 700;
            box-shadow: var(--shadow-sm);
        }

        .dot{
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #23b26d;
            box-shadow: 0 0 0 6px rgba(35,178,109,.15);
        }

        .hero-title{
            font-size: clamp(2.6rem, 5vw, 4rem);
            line-height: .97;
            font-weight: 900;
            letter-spacing: -.05em;
            margin: 0 0 18px;
        }

        .brand-gradient{
            background: linear-gradient(135deg, var(--green-700), var(--green-600));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-copy{
            font-size: 1.05rem;
            line-height: 1.9;
            color: var(--text-soft);
            max-width: 620px;
            margin-bottom: 28px;
        }

        .hero-actions{
            display:flex;
            flex-wrap:wrap;
            gap:14px;
            margin-bottom: 28px;
        }

        .hero-meta{
            display:flex;
            flex-wrap:wrap;
            gap:12px;
        }

        .hero-meta-pill{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:10px 14px;
            background: rgba(255,255,255,.88);
            border:1px solid #dceee5;
            border-radius: 999px;
            color: var(--green-700);
            font-size: .88rem;
            font-weight: 700;
            box-shadow: var(--shadow-sm);
        }

        .hero-preview{
            background: linear-gradient(180deg, #ffffff 0%, #f8fcfa 100%);
            border: 1px solid #deefe7;
            border-radius: 30px;
            padding: 26px;
            box-shadow: var(--shadow-lg);
            position: relative;
        }

        .hero-preview::before{
            content:"";
            position:absolute;
            inset:0;
            border-radius:30px;
            padding:1px;
            background: linear-gradient(135deg, rgba(33,140,106,.26), rgba(33,140,106,0));
            -webkit-mask:
                linear-gradient(#fff 0 0) content-box,
                linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events:none;
        }

        .preview-top{
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom: 22px;
        }

        .preview-title{
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--green-900);
        }

        .preview-sub{
            font-size: .85rem;
            color: var(--text-soft);
            margin-top: 4px;
        }

        .preview-badge{
            padding:8px 12px;
            border-radius:999px;
            background: var(--green-100);
            color: var(--green-700);
            font-size: .8rem;
            font-weight: 700;
        }

        .preview-stack{
            display:grid;
            gap: 14px;
        }

        .preview-card{
            background:#fff;
            border:1px solid #e4f0ea;
            border-radius: 20px;
            padding: 18px;
            box-shadow: var(--shadow-sm);
        }

        .preview-card h4{
            margin:0 0 6px;
            font-size: .96rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .preview-card p{
            margin:0;
            font-size: .88rem;
            line-height: 1.7;
            color: var(--text-soft);
        }

        .section-title{
            font-size: clamp(2rem, 3vw, 3rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -.03em;
        }

        .section-sub{
            color: var(--text-soft);
            font-size: 1rem;
            line-height: 1.8;
            max-width: 760px;
            margin: 0 auto;
        }

        .about-wrap{
            display:grid;
            grid-template-columns: 1fr 1fr;
            gap: 34px;
            align-items: stretch;
        }

        .info-panel{
            background:#fff;
            border:1px solid #e3efe9;
            border-radius: 28px;
            padding: 30px;
            box-shadow: var(--shadow-sm);
        }

        .check-list{
            display:grid;
            gap: 18px;
            margin-top: 20px;
        }

        .check-item{
            display:flex;
            gap: 14px;
            align-items:flex-start;
        }

        .check-badge{
            width: 34px;
            height: 34px;
            flex-shrink:0;
            border-radius: 12px;
            display:flex;
            align-items:center;
            justify-content:center;
            background: var(--green-100);
            color: var(--green-700);
        }

        .soft-panel{
            background: linear-gradient(135deg, #f4fbf7, #eef8f3);
            border:1px solid #dbeee5;
            border-radius: 28px;
            padding: 30px;
            box-shadow: var(--shadow-sm);
        }

        .kpi-row{
            display:grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 16px;
        }

        .kpi{
            background:#fff;
            border-radius: 20px;
            padding: 22px;
            border:1px solid #e3efe9;
            text-align:center;
        }

        .kpi .num{
            font-size: 2rem;
            font-weight: 900;
            color: var(--green-700);
            line-height: 1;
            margin-bottom: 8px;
        }

        .kpi .txt{
            font-size: .88rem;
            color: var(--text-soft);
        }

        .feature-grid{
            display:grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 22px;
        }

        .feature-card{
            background: #fff;
            border:1px solid #e3efe9;
            border-radius: 24px;
            padding: 26px;
            box-shadow: var(--shadow-sm);
            transition: .28s ease;
        }

        .feature-card:hover{
            transform: translateY(-6px);
            box-shadow: var(--shadow-md);
        }

        .icon-wrap{
            width: 50px;
            height: 50px;
            border-radius: 16px;
            display:flex;
            align-items:center;
            justify-content:center;
            background: linear-gradient(135deg, var(--green-700), var(--green-600));
            color: #fff;
            margin-bottom: 18px;
        }

        .feature-card h3{
            font-size: 1.05rem;
            font-weight: 800;
            margin: 0 0 10px;
            color: var(--text-main);
        }

        .feature-card p{
            margin: 0;
            color: var(--text-soft);
            line-height: 1.75;
            font-size: .94rem;
        }

        .cta-section{
            background: linear-gradient(135deg, var(--green-900), var(--green-700));
            color:#fff;
            border-radius: 34px;
            padding: 42px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .cta-section::before{
            content:"";
            position:absolute;
            inset:0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,.13), transparent 30%),
                radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 30%);
            pointer-events:none;
        }

        .cta-grid{
            display:grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 26px;
            align-items:center;
            position:relative;
            z-index:1;
        }

        .cta-list{
            display:grid;
            gap: 12px;
            margin-top: 22px;
        }

        .cta-list-item{
            display:flex;
            gap: 12px;
            align-items:flex-start;
            background: rgba(255,255,255,.08);
            border:1px solid rgba(255,255,255,.12);
            border-radius: 18px;
            padding: 14px 16px;
        }

        .contact-card{
            background: rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.14);
            border-radius: 26px;
            padding: 28px;
        }

        .contact-card h3{
            margin:0 0 12px;
            font-size:1.3rem;
            font-weight:800;
        }

        .contact-card p{
            color: rgba(255,255,255,.84);
            line-height: 1.8;
            margin-bottom: 22px;
        }

        .contact-link{
            display:flex;
            align-items:center;
            gap: 12px;
            color:#fff;
            text-decoration:none;
            padding: 12px 0;
            border-top:1px solid rgba(255,255,255,.12);
        }

        .big-footer{
            background: linear-gradient(180deg, #103327 0%, #0c291f 100%);
            color:#d6ece2;
            margin-top: 0;
        }

        .footer-grid{
            display:grid;
            grid-template-columns: 1.2fr .8fr .8fr .9fr;
            gap: 34px;
        }

        .footer-brand{
            max-width: 360px;
        }

        .footer-title{
            font-size: 1rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 14px;
        }

        .footer-text{
            color:#cde6dc;
            line-height: 1.9;
            font-size: .95rem;
        }

        .footer-list{
            display:grid;
            gap: 10px;
        }

        .footer-link{
            color:#d4ece2;
            text-decoration:none;
            transition:.25s ease;
        }

        .footer-link:hover{
            color:#fff;
            transform: translateX(2px);
        }

        .footer-bottom{
            border-top:1px solid rgba(255,255,255,.10);
            margin-top: 34px;
            padding-top: 22px;
            display:flex;
            flex-direction:column;
            gap:10px;
        }

        @media (max-width: 1024px){
            .hero-clean,
            .about-wrap,
            .cta-grid,
            .feature-grid,
            .footer-grid{
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px){
            .top-nav .desktop-nav{
                display:none;
            }

            .hero-title{
                font-size: 2.55rem;
            }

            .kpi-row{
                grid-template-columns: 1fr;
            }

            .container-wrap{
                padding: 0 16px;
            }

            .cta-section{
                padding: 28px 22px;
            }
        }
    </style>
</head>
<body class="antialiased">
    <nav class="top-nav">
        <div class="container-wrap">
            <div class="flex items-center justify-between py-4">
                <a href="/" class="flex items-center gap-3">
                    <img src="/images/chipper-farms-logo.jpg" alt="Chipper Farms Logo" class="h-11 w-auto rounded-xl shadow-sm border border-green-100">
                    <!-- <div>
                        <div class="text-lg font-extrabold text-[var(--green-900)] leading-none">Chipper Farms</div>
                        <div class="text-xs text-slate-500 mt-1">Smart pig farm management</div>
                    </div> -->
                </a>

                <div class="desktop-nav flex items-center gap-8">
                    <a href="#home" class="nav-link">Home</a>
                    <a href="#about" class="nav-link">About</a>
                    <a href="#features" class="nav-link">Features</a>
                    <a href="#contact" class="nav-link">Contact</a>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('login') }}" class="btn-primary">
                        Sign In
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <section id="home" class="hero-bg">
        <div class="container-wrap py-20 md:py-28 relative z-10">
            <div class="hero-clean">
                <div>
                    <div class="hero-badge mb-6 mt-6">
                        <span class="dot"></span>
                        Smart farm management for Chipper Farms
                    </div>

                    <h1 class="hero-title">
                        Run your farm with
                        <span class="brand-gradient">better structure and better visibility.</span>
                    </h1>

                    <p class="hero-copy">
                        Chipper Farms brings breeding, weight tracking, stock records, feed operations, and farm reporting into one organized system so your team can work with more clarity every day.
                    </p>

                    <div class="hero-actions">
                        <a href="{{ route('login') }}" class="btn-primary">
                            Access System
                        </a>
                        <a href="#features" class="btn-secondary">
                            View Features
                        </a>
                    </div>

                    <div class="hero-meta mb-6">
                        <div class="hero-meta-pill">Breeding records</div>
                        <div class="hero-meta-pill">Weight monitoring</div>
                        <div class="hero-meta-pill">Feed operations</div>
                    </div>
                </div>

                <div class="hero-preview">
                    <div class="preview-top">
                        <div>
                            <div class="preview-title">Platform overview</div>
                            <div class="preview-sub">A simpler look at what the system helps you manage</div>
                        </div>
                        <div class="preview-badge">All-in-one</div>
                    </div>

                    <div class="preview-stack">
                        <div class="preview-card">
                            <h4>Operational control</h4>
                            <p>Manage important farm activities from one central system instead of scattered records.</p>
                        </div>

                        <div class="preview-card">
                            <h4>Performance tracking</h4>
                            <p>Monitor breeding progress, weight changes, and key farm data with more consistency.</p>
                        </div>

                        <div class="preview-card">
                            <h4>Better planning</h4>
                            <p>Use clean records and summaries to support daily action and long-term farm decisions.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="py-20">
        <div class="container-wrap">
            <div class="text-center mb-14">
                <h2 class="section-title mb-4">Built for modern pig farm management</h2>
                <p class="section-sub">
                    Chipper Farms is not just a record book. It is a working system that helps farm teams manage animals, breeding activities, monitoring, and reporting in a more reliable and professional way.
                </p>
            </div>

            <div class="about-wrap">
                <div class="info-panel">
                    <div class="text-sm font-bold uppercase tracking-[0.18em] text-[var(--green-700)] mb-3">Why it matters</div>
                    <h3 class="text-2xl font-extrabold text-[var(--text-main)] mb-4">Better records lead to better farm results.</h3>
                    <p class="text-[var(--text-soft)] leading-8">
                        Farm decisions become easier when your data is organized. From breeding schedules to growth monitoring and stock visibility, the system helps reduce guesswork and improve day-to-day control.
                    </p>

                    <div class="check-list">
                        <div class="check-item">
                            <div class="check-badge">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-bold text-[var(--text-main)]">Improved organization</div>
                                <div class="text-sm text-[var(--text-soft)] mt-1">Keep important farm records consistent, accessible, and easy to update.</div>
                            </div>
                        </div>

                        <div class="check-item">
                            <div class="check-badge">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-bold text-[var(--text-main)]">Better monitoring</div>
                                <div class="text-sm text-[var(--text-soft)] mt-1">Follow breeding progress, weight trends, and key farm activities with more confidence.</div>
                            </div>
                        </div>

                        <div class="check-item">
                            <div class="check-badge">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-bold text-[var(--text-main)]">Clearer reporting</div>
                                <div class="text-sm text-[var(--text-soft)] mt-1">Support operational reviews and planning with records that are easier to analyze.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="soft-panel">
                    <div class="text-sm font-bold uppercase tracking-[0.18em] text-[var(--green-700)] mb-3">What the platform supports</div>
                    <h3 class="text-2xl font-extrabold text-[var(--text-main)] mb-6">A more connected farm workflow.</h3>

                    <div class="kpi-row">
                        <div class="kpi">
                            <div class="num">Breeding</div>
                            <div class="txt">Mating, gestation, and farrowing tracking</div>
                        </div>
                        <div class="kpi">
                            <div class="num">Weights</div>
                            <div class="txt">Growth monitoring and standards comparison</div>
                        </div>
                        <div class="kpi">
                            <div class="num">Records</div>
                            <div class="txt">Structured animal and farm data management</div>
                        </div>
                        <div class="kpi">
                            <div class="num">Reports</div>
                            <div class="txt">Cleaner summaries for review and planning</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="py-20 bg-[var(--green-50)]">
        <div class="container-wrap">
            <div class="text-center mb-14">
                <h2 class="section-title mb-4">Core system features</h2>
                <p class="section-sub">
                    Chipper Farms helps centralize the most important parts of pig farm operations in one system.
                </p>
            </div>

            <div class="feature-grid">
                <div class="feature-card">
                    <div class="icon-wrap">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6m6 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14"/>
                        </svg>
                    </div>
                    <h3>Weight Management</h3>
                    <p>Capture animal weights, compare against standards, review trends, and spot underperformance earlier.</p>
                </div>

                <div class="feature-card">
                    <div class="icon-wrap">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v11a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3>Breeding Workflow</h3>
                    <p>Track mating, pregnancy checks, expected dates, farrowing events, and related breeding activity in one flow.</p>
                </div>

                <div class="feature-card">
                    <div class="icon-wrap">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V7a2 2 0 00-2-2h-3m-6 0H6a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2v-1M8 17h8M8 13h5"/>
                        </svg>
                    </div>
                    <h3>Farm Reporting</h3>
                    <p>View summaries and reports that support better planning, better oversight, and more informed management decisions.</p>
                </div>

                <div class="feature-card">
                    <div class="icon-wrap">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12"/>
                        </svg>
                    </div>
                    <h3>Feed Operations</h3>
                    <p>Manage raw materials, purchase requests, feed mixing, and feed projections as part of the wider farm workflow.</p>
                </div>

                <div class="feature-card">
                    <div class="icon-wrap">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h5M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/>
                        </svg>
                    </div>
                    <h3>Stock Visibility</h3>
                    <p>Monitor available stock, movement records, and low-stock situations to support timely action.</p>
                </div>

                <div class="feature-card">
                    <div class="icon-wrap">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2m-1 0v14m-7-7h14"/>
                        </svg>
                    </div>
                    <h3>Scalable Structure</h3>
                    <p>The system is designed to grow with the farm, making it easier to add more operational modules over time.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="contact" class="py-20">
        <div class="container-wrap">
            <div class="cta-section">
                <div class="cta-grid">
                    <div>
                        <div class="text-sm font-bold uppercase tracking-[0.18em] text-green-100 mb-3">Ready to work smarter?</div>
                        <h2 class="text-3xl md:text-4xl font-extrabold leading-tight mb-4">
                            Give your team a cleaner and more professional way to manage farm operations.
                        </h2>
                        <p class="text-green-100 leading-8 max-w-2xl">
                            Use Chipper Farms to centralize important records, improve monitoring, and support more confident farm management decisions.
                        </p>

                        <div class="cta-list">
                            <div class="cta-list-item">
                                <div class="mt-1">
                                    <svg class="w-5 h-5 text-green-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold">Centralized management</div>
                                    <div class="text-sm text-green-100/90 mt-1">Keep farm activities and records easier to manage from one system.</div>
                                </div>
                            </div>

                            <div class="cta-list-item">
                                <div class="mt-1">
                                    <svg class="w-5 h-5 text-green-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold">Better operational oversight</div>
                                    <div class="text-sm text-green-100/90 mt-1">See breeding, weight, stock, and reporting information in a more structured way.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="contact-card">
                        <h3>Start here</h3>
                        <p>
                            For internal users, the main action on this page should be simple and clear: sign in to access the platform.
                        </p>

                        <a href="{{ route('login') }}" class="btn-secondary" style="width:100%; justify-content:center; margin-bottom:12px;">
                            Sign In to Continue
                        </a>

                        <a href="mailto:support@chipperfarms.com" class="contact-link">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8"/>
                            </svg>
                            support@chipperfarms.com
                        </a>

                        <a href="tel:+233000000000" class="contact-link">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1"/>
                            </svg>
                            Contact support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="big-footer">
        <div class="container-wrap py-16 md:py-20">
            <div class="footer-grid">
                <div class="footer-brand mt-6">
                    <div class="flex items-center gap-3 mb-5">
                        <img src="/images/chipper-farms-logo.jpg" alt="Chipper Farms Logo" class="h-12 w-auto rounded-xl border border-white/10">
                        <!-- <div>
                            <div class="font-bold text-white text-lg">Chipper Farms</div>
                            <div class="text-sm text-green-200">Farm management platform</div>
                        </div> -->
                    </div>

                    <p class="footer-text">
                        Chipper Farms is designed to support more organized pig farm operations through cleaner records, improved monitoring, and better decision support across the farm workflow.
                    </p>
                </div>

                <div>
                    <h3 class="footer-title mt-6">Navigation</h3>
                    <div class="footer-list">
                        <a href="#home" class="footer-link">Home</a>
                        <a href="#about" class="footer-link">About</a>
                        <a href="#features" class="footer-link">Features</a>
                        <a href="#contact" class="footer-link">Contact</a>
                    </div>
                </div>

                <div>
                    <h3 class="footer-title mt-6">System</h3>
                    <div class="footer-list">
                        <a href="{{ route('login') }}" class="footer-link">Sign In</a>
                        <a href="#features" class="footer-link">Core Modules</a>
                        <a href="#about" class="footer-link">How It Works</a>
                    </div>
                </div>

                <div>
                    <h3 class="footer-title mt-6">Contact</h3>
                    <div class="footer-list">
                        <a href="mailto:support@chipperfarms.com" class="footer-link">support@chipperfarms.com</a>
                        <a href="tel:+233000000000" class="footer-link">+233 24 485 3386</a>
                        <span class="text-sm text-green-200">Accra, Ghana</span>
                    </div>
                </div>
            </div>

            <div class="footer-bottom ">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 text-sm text-green-200 mb-4">
                    <div>&copy; {{ date('Y') }} Chipper Farms. Powered by Innosol Limited All rights reserved.</div>
                    <div>Built for efficient pig farm operations and smarter farm management.</div>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>