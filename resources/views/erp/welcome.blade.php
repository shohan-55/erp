<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6faf9">
    <meta name="description" content="DhakaWala ERP brings attendance, approvals, payroll workflows and practice operations into one thoughtfully designed workspace for modern teams.">
    <meta property="og:type" content="website">
    <meta property="og:title" content="DhakaWala ERP — Clarity for the work that matters">
    <meta property="og:description" content="A beautifully connected workspace for people, approvals and practice operations.">
    <meta property="og:url" content="{{ url('/') }}">
    <title>DhakaWala ERP — Clarity for the work that matters</title>
    {{-- This page deliberately has no Vite dependency: updating the Blade view
         cannot leave the separate cPanel public_html asset bundle out of sync. --}}
    <style>
        :root{color-scheme:light;--ink:#132b36;--muted:#687d84;--brand:#20bea9;--brand-deep:#087e76;--paper:#f6faf9;--line:#e0ebe8;--display:ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Arial,sans-serif}
        *,*::before,*::after{box-sizing:border-box}
        html{scroll-behavior:smooth;scroll-padding-top:96px}
        body{margin:0;background:var(--paper);color:var(--ink);font-family:var(--display);font-size:16px;-webkit-font-smoothing:antialiased}
        a{color:inherit;text-decoration:none}
        button,input,summary{font:inherit}
        a:focus-visible,summary:focus-visible{outline:3px solid #087e76;outline-offset:5px;border-radius:5px}
        ::selection{background:var(--brand);color:#fff}
        .container{width:min(1192px,calc(100% - 48px));margin-inline:auto}
        .skip{position:absolute;top:-60px;left:16px;background:var(--brand);color:#fff;padding:13px 20px;z-index:100;border-radius:9px;font-weight:800}
        .skip:focus{top:14px}
        .site-header{position:relative;z-index:20;border-bottom:1px solid rgba(27,32,34,.07);background:rgba(246,250,249,.98)}
        .nav{height:88px;display:flex;align-items:center;justify-content:space-between;gap:28px}
        .brand{display:inline-flex;align-items:center;gap:11px;flex-shrink:0}
        .brand-mark{display:grid;grid-template-columns:repeat(3,5px);align-items:end;justify-content:center;gap:3px;width:40px;height:40px;border-radius:13px;background:var(--brand);transform:rotate(-5deg);box-shadow:0 4px 14px rgba(19,172,157,.15)}
        .brand-mark span{display:block;width:5px;background:#ffffff;border-radius:4px 4px 2px 2px}
        .brand-mark span:nth-child(1){height:13px}.brand-mark span:nth-child(2){height:23px}.brand-mark span:nth-child(3){height:17px}
        .brand-copy{font-size:20px;line-height:1;letter-spacing:-.8px;font-weight:850}
        .brand-copy small{display:block;margin-top:5px;font-size:9px;line-height:1;letter-spacing:2.9px;color:#7d8589;font-weight:800}
        .desktop-links{display:flex;align-items:center;gap:33px;margin-left:auto}
        .desktop-links a{font-size:13px;color:#525a61;font-weight:650;transition:color .2s}
        .desktop-links a:hover,.footer-links a:hover{color:#000}
        .nav-actions{display:flex;align-items:center;gap:15px}
        .nav-signin{font-size:13px;font-weight:750;white-space:nowrap}
        .button{display:inline-flex;align-items:center;justify-content:center;gap:10px;border-radius:12px;min-height:49px;padding:0 20px;border:1px solid transparent;font-size:13px;font-weight:800;white-space:nowrap;transition:transform .2s,box-shadow .2s,background .2s}
        .button:hover{transform:translateY(-2px)}
        .button svg,.text-link svg{width:17px;height:17px;flex-shrink:0}
        .button-dark{background:#102c39;color:#fff;box-shadow:0 9px 18px rgba(19,23,26,.08)}
        .button-dark:hover{background:#204250;box-shadow:0 12px 24px rgba(19,23,26,.15)}
        .button-teal{background:var(--brand);color:#fff;border-color:var(--brand);box-shadow:0 12px 28px rgba(19,172,157,.15)}
        .button-teal:hover{background:#23c5b4}
        .button-outline{border-color:#d9dcd9;background:#fff;color:#1d2428}
        .button-outline:hover{border-color:#b8bcb9;background:#fcfcfa}
        .mobile-menu{display:none}
        .hero{position:relative;isolation:isolate;overflow:hidden;padding:77px 0 84px;background:radial-gradient(ellipse 730px 540px at 98% 27%,rgba(19,172,157,.10),transparent 74%),radial-gradient(ellipse 520px 340px at -11% 90%,rgba(218,226,216,.19),transparent 75%),var(--paper)}
        .hero::before{position:absolute;z-index:-1;content:"";inset:0;opacity:.39;background-image:radial-gradient(#d9ddd7 .6px,transparent .6px);background-size:22px 22px;mask-image:linear-gradient(90deg,transparent 10%,#000 100%)}
        .hero-grid{display:grid;grid-template-columns:minmax(0,.99fr) minmax(0,1.01fr);gap:56px;align-items:center}
        .eyebrow{display:inline-flex;align-items:center;gap:9px;min-height:31px;padding:0 12px;background:#fff;border:1px solid #e8e9e3;border-radius:999px;font-size:10px;font-weight:850;letter-spacing:1.2px;text-transform:uppercase;color:#525c5e}
        .eyebrow .dot{height:7px;width:7px;background:#13ac9d;border-radius:50%;box-shadow:0 0 0 4px #d8f5ef}
        .hero h1{margin:26px 0 20px;font-size:clamp(45px,5.1vw,75px);line-height:1.055;letter-spacing:-.075em;font-weight:850;max-width:650px}
        .hero h1 em{font-style:normal;color:#63958f}
        .hero h1 .underline{position:relative;white-space:nowrap;color:#1a1d1e}
        .hero h1 .underline::after{content:"";position:absolute;z-index:-1;bottom:3px;left:1px;right:-4px;height:15px;background:var(--brand);transform:skew(-7deg)}
        .hero-description{max-width:483px;color:#5e696e;font-size:16px;line-height:1.86}
        .hero-buttons{display:flex;flex-wrap:wrap;gap:12px;margin-top:31px}
        .hero-note{display:flex;align-items:center;gap:10px;margin-top:28px;font-size:12px;color:#7a8588}
        .hero-note .tiny-icon{display:grid;place-items:center;height:25px;width:25px;background:#e1f5ef;border-radius:50%;color:#137d75}
        .hero-note svg{width:13px;height:13px}
        .preview-area{position:relative;min-width:0;padding:22px 0 25px}
        .preview-halo{position:absolute;inset:9% 1% -2% 7%;background:radial-gradient(circle at 65% 42%,rgba(42,196,173,.24),rgba(37,197,176,.08) 51%,transparent 73%);filter:blur(22px);z-index:-1}
        .preview-window{position:relative;width:100%;border:1px solid #e2e6e3;overflow:hidden;border-radius:20px;background:#fff;box-shadow:0 36px 78px rgba(26,35,30,.105),0 2px 10px rgba(26,35,30,.025);transform:rotate(-1.2deg)}
        .preview-titlebar{height:47px;display:flex;align-items:center;justify-content:space-between;padding:0 19px;background:#fff;border-bottom:1px solid #edf0ec}
        .browser-dots{display:flex;gap:5px}.browser-dots i{height:8px;width:8px;border:1px solid #cfd5d0;background:#dceae6;border-radius:50%}.browser-dots i:first-child{background:var(--brand);border-color:var(--brand-deep)}
        .preview-caption{font-size:10px;font-weight:700;letter-spacing:.08em;color:#8a9495;text-transform:uppercase}
        .preview-content{display:grid;grid-template-columns:87px minmax(0,1fr);min-height:355px}
        .preview-rail{display:flex;flex-direction:column;align-items:center;gap:21px;background:#102c39;padding:24px 10px}
        .rail-logo{width:28px;height:28px;background:var(--brand);border-radius:9px;display:grid;place-items:center;font-size:16px;font-weight:950}
        .rail-item{width:34px;height:34px;display:grid;place-items:center;color:#70777c;border-radius:9px}
        .rail-item svg{width:18px;height:18px;stroke:currentColor}
        .rail-item.active{background:#20434a;color:var(--brand)}
        .rail-bottom{margin-top:auto;width:26px;height:26px;border-radius:50%;background:#697475;color:white;display:grid;place-items:center;font-weight:800;font-size:11px}
        .preview-dashboard{min-width:0;padding:25px 23px;background:#f6faf9}
        .dashboard-overline{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#939d9e}
        .mini-live{display:flex;align-items:center;gap:5px;color:#178474;font-size:9px;font-weight:800;letter-spacing:0;text-transform:none}.mini-live::before{content:"";width:5px;height:5px;background:#10a98e;border-radius:50%}
        .preview-dashboard h2{font-size:22px;letter-spacing:-.8px;margin:12px 0 4px;line-height:1.2}
        .preview-dashboard>.desc{font-size:10px;color:#98a0a0;margin:0 0 21px}
        .mock-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
        .mock-stat{background:#fff;border:1px solid #eff0eb;border-radius:11px;padding:13px 11px 12px;min-width:0}
        .stat-label{font-size:9px;color:#8b9493;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .stat-title{font-size:12px;font-weight:900;margin-top:9px;letter-spacing:-.3px}
        .stat-mark{display:inline-block;vertical-align:middle;width:7px;height:7px;border-radius:50%;background:#58a67b;margin-right:4px}.stat-mark.teal{background:#1db39c}.stat-mark.blue{background:#88a3b7}
        .mock-panels{display:grid;grid-template-columns:1.12fr .88fr;gap:10px;margin-top:11px}
        .mock-panel{min-width:0;background:#fff;border:1px solid #eff0eb;border-radius:12px;padding:15px 13px}
        .mock-panel h3{font-size:10px;font-weight:850;margin:0;letter-spacing:-.2px}
        .mock-panel p{font-size:8px;color:#9ba3a2;margin:5px 0 0}
        .bars{height:89px;display:flex;align-items:flex-end;justify-content:space-between;gap:8px;margin-top:12px;border-bottom:1px solid #ecf0ec;background:repeating-linear-gradient(to top,#edf0eb 0,#edf0eb 1px,transparent 1px,transparent 26px)}
        .bars i{display:block;flex:1;max-width:25px;border-radius:5px 5px 1px 1px;background:#e4efec}.bars i:nth-child(1){height:32%}.bars i:nth-child(2){height:58%}.bars i:nth-child(3){height:45%;background:#a7e5d8}.bars i:nth-child(4){height:79%;background:var(--brand)}.bars i:nth-child(5){height:62%;background:#cceae5}.bars i:nth-child(6){height:92%;background:#bde9dd}.bars i:nth-child(7){height:75%;background:#13ac9d}
        .bar-labels{display:flex;justify-content:space-between;font-size:7px;color:#a3abaa;padding-top:7px}
        .review-list{margin-top:13px;display:grid;gap:12px}.review-item{display:flex;align-items:center;gap:8px;font-size:8px;font-weight:750;color:#667075}
        .review-icon{display:grid;place-items:center;flex:0 0 22px;width:22px;height:22px;background:#eef8f6;border-radius:7px;font-weight:800;color:#738175;font-size:9px}
        .review-item small{margin-left:auto;background:#e7f7f1;color:#107d71;font-weight:800;padding:4px 6px;border-radius:5px;font-size:7px}
        .float-chip{position:absolute;bottom:6px;left:-27px;display:flex;align-items:center;gap:12px;padding:12px 15px;border:1px solid #e5e9e4;border-radius:13px;background:white;box-shadow:0 17px 30px rgba(33,40,31,.1);transform:rotate(2deg)}
        .float-chip .chip-icon{width:32px;height:32px;display:grid;place-items:center;background:#e1f5ef;border-radius:9px;color:#0a8678}
        .float-chip strong{display:block;font-size:11px}.float-chip small{display:block;color:#9da6a3;font-size:9px;margin-top:3px}
        .float-chip svg{width:17px;height:17px}
        .showcase-note{position:absolute;right:8px;bottom:0;color:#909a95;font-size:10px}
        .proof{border-block:1px solid #e3ece8;background:#fff}
        .proof-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;padding-block:25px}
        .proof-item{display:flex;align-items:center;justify-content:center;gap:12px}
        .proof-item svg{width:20px;height:20px;color:#737d77;stroke-width:1.7}
        .proof-item span{font-size:12px;font-weight:750;color:#636d6b;letter-spacing:-.12px}
        .section{padding:107px 0}
        .section-heading{max-width:600px;margin-inline:auto;text-align:center}
        .kicker{font-size:11px;font-weight:850;letter-spacing:1.6px;text-transform:uppercase;color:#108277}
        .section h2,.access h2,.dark-feature h2{font-size:clamp(34px,3.8vw,53px);line-height:1.12;letter-spacing:-.056em;font-weight:850;margin:16px 0}
        .section-heading p{margin:0;color:#6d7678;font-size:15px;line-height:1.8}
        .feature-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:45px}
        .feature-card{min-height:215px;padding:26px;border:1px solid #e8eae5;border-radius:19px;background:#fff;transition:transform .22s,border-color .22s,box-shadow .22s}
        .feature-card:hover{transform:translateY(-4px);border-color:#e6dfb0;box-shadow:0 20px 44px rgba(32,35,26,.05)}
        .feature-icon{width:44px;height:44px;border-radius:13px;background:#e8f5f1;display:grid;place-items:center;color:#2a3331}
        .feature-icon svg{width:21px;height:21px;stroke-width:1.7}
        .feature-card h3{font-size:18px;line-height:1.3;letter-spacing:-.55px;margin:23px 0 9px}
        .feature-card p{font-size:13px;line-height:1.8;margin:0;color:#778081}
        .dark-feature{position:relative;isolation:isolate;overflow:hidden;margin:0 auto;border-radius:28px;background:#102c39;color:#fff;padding:66px 70px;display:grid;grid-template-columns:.95fr 1.05fr;gap:70px}
        .dark-feature::before{content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(circle at 91% 17%,rgba(31,188,168,.18),transparent 42%),radial-gradient(circle at 8% 94%,rgba(255,255,255,.035),transparent 45%)}
        .dark-feature .kicker{color:#8be7d7}
        .dark-feature h2{max-width:460px;font-size:clamp(34px,3.5vw,48px)}
        .dark-feature p{font-size:14px;color:#aeb6b6;line-height:1.9;max-width:440px}
        .dark-feature .text-link{display:inline-flex;align-items:center;gap:10px;margin-top:20px;font-weight:800;font-size:13px;color:#8be7d7}
        .safety-list{display:grid;gap:11px}
        .safety-item{display:flex;align-items:flex-start;gap:15px;border:1px solid rgba(255,255,255,.1);border-radius:13px;background:rgba(255,255,255,.035);padding:17px 19px}
        .safety-icon{flex:0 0 33px;width:33px;height:33px;border:1px solid rgba(63,211,192,.40);background:rgba(63,211,192,.11);border-radius:9px;display:grid;place-items:center;color:#8be7d7}
        .safety-icon svg{width:17px;height:17px}
        .safety-item h3{font-size:13px;margin:1px 0 5px}
        .safety-item p{font-size:11px;margin:0;line-height:1.6;color:#a8b1b2}
        .flow-section{padding:108px 0 100px}
        .flow-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:32px;margin-top:49px}
        .flow-card{position:relative;border-top:1px solid #dfe3df;padding-top:25px}
        .flow-card:not(:last-child)::after{content:"";position:absolute;top:-3px;right:-16px;width:6px;height:6px;border-radius:50%;background:#b9c2b9}
        .flow-num{display:inline-flex;align-items:center;justify-content:center;height:36px;min-width:36px;padding:0 8px;border:1px solid #d7ece6;background:#dff7f1;border-radius:11px;font-size:12px;font-weight:900}
        .flow-card h3{font-size:19px;letter-spacing:-.45px;margin:23px 0 9px}
        .flow-card p{font-size:13px;color:#798283;line-height:1.8;margin:0;max-width:300px}
        .access{position:relative;background:#fff;border-block:1px solid #edf0eb}
        .access-grid{display:grid;grid-template-columns:1fr 1fr;gap:75px;align-items:center;padding-block:94px}
        .access h2{margin:14px 0}
        .access p{color:#727d7d;font-size:14px;line-height:1.9;max-width:480px}
        .access-card{border:1px solid #e3e8df;border-radius:20px;background:linear-gradient(143deg,#fcfcf8,#f6f8f2);padding:28px;box-shadow:0 16px 45px rgba(26,42,30,.04)}
        .access-card .mini-title{font-size:11px;font-weight:850;letter-spacing:.08em;text-transform:uppercase;color:#7a857e}
        .url-bar{display:flex;align-items:center;gap:11px;margin-top:20px;padding:15px 17px;border:1px solid #e3e9df;border-radius:11px;background:#fff;font-size:13px;font-weight:700;word-break:break-word}
        .url-lock{width:14px;height:14px;color:#679176;flex-shrink:0}
        .url-bar strong{font-weight:850;color:#1b211e}.url-bar em{font-style:normal;color:#8d9691;font-weight:550}
        .access-buttons{display:flex;flex-wrap:wrap;gap:10px;margin-top:21px}
        .access-small{font-size:10px;color:#929e99;margin-top:17px;line-height:1.6}
        .faq-section{padding:94px 0 103px}
        .faq-layout{display:grid;grid-template-columns:.7fr 1.3fr;gap:90px}
        .faq-layout h2{margin:15px 0}
        .faq-layout .intro{color:#788283;font-size:14px;line-height:1.8;max-width:270px}
        .faq-list{border-top:1px solid #dfe4df}
        .faq-list details{border-bottom:1px solid #dfe4df;padding:20px 0}
        .faq-list summary{display:flex;align-items:center;justify-content:space-between;gap:15px;cursor:pointer;list-style:none;font-size:15px;font-weight:760;line-height:1.4}
        .faq-list summary::-webkit-details-marker{display:none}
        .faq-list summary::after{content:"+";font-size:25px;font-weight:400;color:#6a736c}
        .faq-list details[open] summary::after{content:"−"}
        .faq-list details p{max-width:570px;margin:13px 40px 0 0;color:#707b79;font-size:13px;line-height:1.8}
        .final-cta{background:linear-gradient(110deg,#086e70,#0c9688);color:#fff;padding:63px 0}
        .cta-row{display:flex;align-items:center;justify-content:space-between;gap:30px}
        .cta-row h2{margin:0;font-size:clamp(30px,3.5vw,46px);letter-spacing:-.05em;line-height:1.12}
        .cta-row p{margin:10px 0 0;font-size:13px;color:#d5f4ef}
        .site-footer{padding:43px 0 28px;background:#0b202e;color:#e8ebeb}
        .footer-main{display:flex;align-items:flex-start;justify-content:space-between;gap:35px}
        .footer-main .brand-mark{width:35px;height:35px;border-radius:10px}.footer-main .brand-copy{font-size:19px;color:#fff}
        .footer-main .brand-copy small{color:#8ca8ad}
        .footer-description{font-size:12px;line-height:1.8;color:#899392;max-width:335px;margin:18px 0 0}
        .footer-links{display:flex;flex-wrap:wrap;gap:25px;padding-top:13px}.footer-links a{font-size:12px;color:#aeb5b3;font-weight:650}
        .footer-bottom{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-top:41px;padding-top:22px;border-top:1px solid #2b4551;font-size:11px;color:#798583}
        .footer-credit{line-height:1.75;color:#9fb6c3;text-wrap:pretty}
        .footer-credit strong{font-weight:800;color:#e1f7f5}
        @media(max-width:1080px){.hero-grid{gap:28px}.hero h1{font-size:clamp(43px,5.1vw,65px)}.dark-feature{gap:30px;padding:52px}.desktop-links{gap:17px}.nav{gap:16px}}
        @media(max-width:900px){.nav{height:76px}.desktop-links{display:none}.mobile-menu{display:block;position:relative}.mobile-menu>summary{display:grid;place-items:center;width:43px;height:43px;border:1px solid #dde1dc;border-radius:10px;cursor:pointer;list-style:none;background:#fff}.mobile-menu>summary::-webkit-details-marker{display:none}.hamburger{width:17px;height:15px;display:flex;flex-direction:column;justify-content:space-between}.hamburger i{height:2px;width:100%;background:#1b2021;border-radius:2px}.mobile-menu[open] .mobile-dropdown{position:absolute;right:0;top:55px;width:min(270px,calc(100vw - 48px));display:grid;gap:4px;padding:11px;border:1px solid #e1e5df;border-radius:14px;background:#fff;box-shadow:0 20px 45px rgba(31,38,31,.14)}.mobile-dropdown a{padding:11px 12px;border-radius:8px;font-size:13px;font-weight:650}.mobile-dropdown a:hover{background:#f7f8f4}.hero{padding:67px 0 68px}.hero-grid{grid-template-columns:1fr;gap:41px}.hero-text{max-width:650px}.hero h1{max-width:620px}.preview-area{width:min(620px,95%);margin-inline:auto}.feature-grid{grid-template-columns:repeat(2,1fr)}.dark-feature{grid-template-columns:1fr;gap:26px}.dark-feature h2{max-width:650px}.access-grid{grid-template-columns:1fr;gap:26px}.faq-layout{grid-template-columns:1fr;gap:32px}.faq-layout .intro{max-width:530px}}
        @media(max-width:600px){.container{width:min(100% - 36px,1192px)}.nav{height:70px}.brand-mark{width:36px;height:36px}.brand-copy{font-size:18px}.nav-signin{display:none}.nav-actions>.button{min-height:39px;padding:0 12px;font-size:11px}.hero{padding:59px 0 65px}.hero h1{font-size:clamp(42px,10.1vw,60px);letter-spacing:-.068em;margin:22px 0 16px}.hero-description{font-size:14px;line-height:1.8}.hero-buttons{gap:10px}.hero-buttons .button{flex:1 1 100%;width:100%;min-width:0}.hero-note{font-size:11px}.preview-area{width:100%;padding:9px 0 26px}.preview-window{border-radius:14px;transform:none}.preview-content{grid-template-columns:48px minmax(0,1fr);min-height:300px}.preview-rail{padding:19px 4px;gap:18px}.rail-logo{height:25px;width:25px}.rail-item{width:27px;height:27px}.rail-item svg{width:15px;height:15px}.preview-dashboard{padding:18px 12px}.preview-dashboard h2{font-size:17px}.mock-stats{gap:5px}.mock-stat{padding:11px 7px}.stat-title{font-size:10px}.stat-label{font-size:8px}.mock-panels{gap:6px}.mock-panel{padding:11px 7px}.review-list{gap:10px}.review-item{font-size:7px;gap:3px}.review-icon{flex-basis:17px;width:17px;height:17px}.review-item small{font-size:6px;padding:3px}.float-chip{left:4px;bottom:3px;padding:9px}.showcase-note{right:0;font-size:9px}.proof-grid{grid-template-columns:1fr;gap:17px;padding-block:24px}.proof-item{justify-content:flex-start;padding-left:10px}.section{padding:77px 0}.section h2,.access h2,.dark-feature h2{font-size:clamp(33px,8vw,42px)}.section-heading p{font-size:14px}.feature-grid{grid-template-columns:1fr;gap:11px;margin-top:29px}.feature-card{min-height:0;padding:22px}.feature-card h3{margin-top:17px}.dark-feature{padding:36px 25px;border-radius:20px}.flow-section{padding:76px 0}.flow-grid{grid-template-columns:1fr;gap:29px;margin-top:36px}.flow-card:not(:last-child)::after{display:none}.access-grid{padding-block:73px}.access-card{padding:21px}.faq-section{padding:73px 0}.faq-list summary{font-size:14px}.cta-row{align-items:flex-start;flex-direction:column}.final-cta{padding:53px 0}.footer-main{flex-direction:column}.footer-bottom{flex-direction:column;align-items:flex-start}.footer-links{gap:17px}}
        @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.button,.feature-card{transition:none}.button:hover,.feature-card:hover{transform:none}}

        /* Platform-only navy + sea-green theme. Tenant-specific workspace branding remains untouched. */
        :root{--ink:#15283b;--muted:#647786;--brand:#20bea9;--brand-deep:#087f82;--paper:#f5f9fb;--line:#e1eaee}
        ::selection{background:#1bc5b2;color:#0b293b}
        .site-header{position:sticky;top:0;z-index:30;border-bottom:1px solid rgba(206,246,244,.13);background:rgba(27,40,79,.98);box-shadow:0 9px 24px rgba(5,23,48,.11);backdrop-filter:blur(18px)}
        .brand-copy{color:#fff;letter-spacing:-.9px}
        .brand-copy small{color:#9dd7d9}
        .brand-mark{background:linear-gradient(145deg,#24d1be,#09858f);box-shadow:0 7px 22px rgba(2,164,165,.22);transform:rotate(-5deg)}
        .desktop-links a,.nav-signin{color:#dae6f1}
        .desktop-links a:hover,.nav-signin:hover{color:#8ef1df}
        .nav-actions>.button-dark{background:#21bcae;color:#0c2940;box-shadow:none}
        .nav-actions>.button-dark:hover{background:#7eeddc;box-shadow:0 10px 28px rgba(43,220,205,.2)}
        .hero{padding:92px 0 108px;color:#fff;background:radial-gradient(ellipse 680px 510px at 88% 54%,rgba(20,190,180,.40),transparent 74%),radial-gradient(ellipse 510px 370px at 2% 100%,rgba(18,109,151,.5),transparent 78%),linear-gradient(123deg,#1d2850 4%,#173e59 55%,#086e72 100%)}
        .hero::before{opacity:.15;background-image:radial-gradient(rgba(228,255,253,.9) .8px,transparent .8px);background-size:22px 22px;mask-image:linear-gradient(90deg,transparent 5%,#000 97%)}
        .hero::after{content:"";position:absolute;z-index:0;right:-145px;top:8%;width:min(54vw,700px);aspect-ratio:1;border:1px solid rgba(153,249,241,.15);border-radius:50%;box-shadow:0 0 0 72px rgba(144,236,230,.035),0 0 0 155px rgba(144,236,230,.025);pointer-events:none}
        .hero-grid{position:relative;z-index:1;gap:63px}
        .eyebrow{color:#a6eee4;background:rgba(14,104,118,.32);border:1px solid rgba(126,246,229,.31);letter-spacing:1.05px}
        .eyebrow .dot{background:#6ff3d1;box-shadow:0 0 0 4px rgba(127,249,223,.17)}
        .hero h1{letter-spacing:-.073em;text-wrap:balance;text-shadow:0 4px 24px rgba(7,25,48,.08)}
        .hero h1 em{color:#8eefd9}
        .hero h1 .underline{color:#fff}
        .hero h1 .underline::after{height:11px;bottom:2px;background:#24c5b5;z-index:-1;opacity:.78}
        .hero-description{color:#d3e6ed;font-size:17px;line-height:1.85}
        .hero .button-dark{background:#21c7b5;border-color:#21c7b5;color:#082d3d;box-shadow:0 15px 35px rgba(5,22,47,.18)}
        .hero .button-dark:hover{background:#8af0df;border-color:#8af0df;box-shadow:0 17px 34px rgba(5,22,47,.27)}
        .hero .button-outline{background:rgba(255,255,255,.075);border-color:rgba(221,249,250,.42);color:#fff}
        .hero .button-outline:hover{background:rgba(255,255,255,.14);border-color:#e1fbf8}
        .hero-note{color:#c4dfdf}
        .hero-note .tiny-icon{background:rgba(72,228,199,.18);color:#91f1d9}
        .preview-halo{inset:3% -4% -4% -3%;background:radial-gradient(circle at 60% 50%,rgba(73,249,217,.37),rgba(78,173,198,.13) 56%,transparent 77%);filter:blur(28px)}
        .preview-window{border:1px solid rgba(221,255,250,.56);box-shadow:0 41px 80px rgba(3,23,51,.34),0 0 0 6px rgba(255,255,255,.035);transform:rotate(-1.5deg)}
        .preview-rail{background:#1c3150}
        .rail-item.active{background:#265e67;color:#7ef5df}
        .preview-dashboard{background:#f5f9fb}
        .preview-titlebar{background:#fcfefe}
        .stat-mark,.bars i:nth-child(4),.bars i:nth-child(7){background:#20bea9}
        .float-chip{border-color:#d9f3ee;box-shadow:0 21px 36px rgba(6,30,51,.24)}
        .proof{border-block:1px solid #dfeaf0;background:#fff}
        .feature-card{border-color:#dce9ed;box-shadow:0 7px 26px rgba(20,55,72,.025)}
        .feature-card:hover{border-color:#8bded1;box-shadow:0 23px 40px rgba(20,85,100,.085)}
        .feature-icon{background:#e3f8f4;color:#117e83}
        .kicker{color:#078d91}
        .dark-feature{background:radial-gradient(circle at 92% 80%,rgba(17,190,171,.18),transparent 49%),linear-gradient(120deg,#1e2c53,#0e4d65);box-shadow:0 26px 54px rgba(8,35,58,.1)}
        .dark-feature .kicker,.dark-feature .text-link{color:#8cf4df}
        .access-card{background:linear-gradient(145deg,#f6fcfb,#eaf7f6);border-color:#d5ebe8}
        .final-cta{background:linear-gradient(110deg,#183c62,#087d80)}
        .site-footer{background:#141f3d}
        .footer-bottom{border-top-color:#32435c}
        .site-footer .brand-mark{background:linear-gradient(145deg,#25cfb8,#0e8990)}
        @media(max-width:900px){.hero{padding:73px 0 80px}.mobile-menu>summary{border-color:rgba(177,247,239,.38);background:rgba(255,255,255,.09)}.hamburger i{background:#e1f7f5}.mobile-menu[open] .mobile-dropdown{background:#f8fcfc;border-color:#c9e3e5}.mobile-dropdown a{color:#24334b}}
        @media(max-width:600px){.hero{padding:65px 0 71px}.hero-grid{gap:35px}.hero::after{top:33%;right:-160px;width:380px;opacity:.55}.hero h1{font-size:clamp(42px,10.2vw,57px)}.hero-description{font-size:15px}.preview-window{transform:none}.float-chip{left:6px}.nav-actions>.button-dark{padding:0 13px}}


        /* Refined photography-led industry experience; overrides only public landing cards. */
        .industry-card{box-shadow:0 11px 27px rgba(19,52,75,.055),0 2px 5px rgba(19,52,75,.018)}
        .industry-card:hover{box-shadow:0 26px 52px rgba(14,65,79,.15);border-color:#5bcebf}
        .industry-visual{background-color:#174a65}
        .industry-visual::before{content:"";position:absolute;inset:0;z-index:0;background:linear-gradient(135deg,rgba(13,31,57,.10),rgba(8,113,120,.1));transition:background .25s ease}
        .industry-card:hover .industry-visual::before{background:linear-gradient(135deg,rgba(13,31,57,.02),rgba(8,113,120,.04))}
        .industry-card h3{letter-spacing:-.042em}
        .industry-bottom{box-shadow:0 12px 32px rgba(10,73,83,.045)}
        @media(prefers-reduced-motion:reduce){.industry-visual::before{transition:none}}
        /* Industry fit: self-contained layout; photos are progressive remote visual enhancements. */
        .industry-section{position:relative;padding:101px 0 105px;background:linear-gradient(180deg,#f0f7f9 0%,#ffffff 42%,#f8fbfc 100%);border-block:1px solid #e0ecf0}
        .industry-intro{display:flex;align-items:end;justify-content:space-between;gap:40px;margin-bottom:34px}
        .industry-intro-main{max-width:725px}
        .industry-overline{display:inline-flex;align-items:center;gap:9px;font-size:11px;font-weight:850;letter-spacing:1.8px;color:#088c91;text-transform:uppercase}
        .industry-overline:before{content:"";width:22px;height:2px;border-radius:2px;background:#12b9a9}
        .industry-intro h2{font-size:clamp(35px,4vw,55px);line-height:1.1;letter-spacing:-.057em;font-weight:850;color:#152e48;margin:16px 0 17px;max-width:730px}
        .industry-intro h2 span{color:#099a95}
        .industry-intro p{max-width:705px;color:#647c89;line-height:1.77;font-size:15px;margin:0}
        .industry-intro-aside{max-width:223px;padding:18px 18px 18px 20px;border-left:2px solid #18b4a8;font-size:13px;font-weight:740;line-height:1.75;color:#426274}
        .industry-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
        .industry-card{overflow:hidden;min-width:0;display:flex;flex-direction:column;background:#fff;border:1px solid #dce9ec;border-radius:22px;box-shadow:0 12px 28px rgba(16,52,74,.035);transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease}
        .industry-card:hover{transform:translateY(-5px);border-color:#80d8cf;box-shadow:0 24px 46px rgba(15,61,80,.12)}
        .industry-visual{position:relative;isolation:isolate;overflow:hidden;aspect-ratio:1.47;border-radius:17px;margin:11px 11px 0;background:linear-gradient(125deg,#204165,#0c918e);background-image:linear-gradient(135deg,rgba(18,42,70,.17),rgba(10,77,82,.16)),var(--industry-photo);background-size:cover;background-position:center}
        .industry-visual::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 50%,rgba(9,30,53,.35) 100%);pointer-events:none}
        .industry-visual .industry-index{position:absolute;top:12px;left:12px;display:inline-grid;place-items:center;min-width:30px;height:27px;padding-inline:8px;z-index:1;border:1px solid rgba(255,255,255,.27);border-radius:8px;background:rgba(9,33,53,.57);color:#fff;font-size:10px;font-weight:850;letter-spacing:.08em;backdrop-filter:blur(10px)}
        .industry-card-content{display:flex;flex-direction:column;flex:1;min-width:0;padding:22px 21px 23px}
        .industry-title-row{display:flex;align-items:start;justify-content:space-between;gap:8px}
        .industry-card h3{margin:0;color:#163149;font-size:clamp(16px,1.65vw,20px);line-height:1.26;font-weight:830;letter-spacing:-.6px}
        .industry-symbol{display:grid;place-items:center;flex:0 0 29px;width:29px;height:29px;border:1px solid #d3f0e9;border-radius:10px;color:#088e86;background:#edf9f7}
        .industry-symbol svg{width:15px;height:15px;stroke-width:1.8}
        .industry-card p{font-size:12px;line-height:1.7;color:#70818b;margin:11px 0 18px}
        .industry-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:auto}
        .industry-tags span{display:inline-flex;align-items:center;min-height:25px;padding:5px 9px;background:#edf7f6;border:1px solid #e2f0ee;border-radius:7px;color:#247772;font-size:10px;font-weight:720;line-height:1.2}
        .industry-bottom{margin-top:33px;padding:24px 29px;border:1px solid #d6e9e8;background:linear-gradient(105deg,#e9f8f6,#f5fbfb);border-radius:18px;display:flex;align-items:center;justify-content:space-between;gap:20px}
        .industry-bottom strong{display:block;color:#193a50;font-size:16px;letter-spacing:-.35px}
        .industry-bottom p{margin:7px 0 0;color:#5f7b83;line-height:1.6;font-size:12px}
        .industry-bottom a{display:inline-flex;align-items:center;justify-content:center;gap:9px;white-space:nowrap;padding:13px 17px;border:1px solid #157f82;border-radius:10px;background:#174a63;color:#fff;font-size:12px;font-weight:780;transition:background .2s,transform .2s}
        .industry-bottom a:hover{background:#087e81;transform:translateY(-2px)}
        .industry-bottom a:focus-visible{outline:3px solid #087e76;outline-offset:4px}
        .industry-bottom a svg{width:15px;height:15px}
        @media(max-width:1080px){.industry-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.industry-card h3{font-size:20px}.industry-visual{aspect-ratio:1.8}}
        @media(max-width:700px){.industry-section{padding:75px 0}.industry-intro{display:block;margin-bottom:27px}.industry-intro h2{font-size:clamp(32px,7.5vw,43px)}.industry-intro p{font-size:14px}.industry-intro-aside{display:none}.industry-grid{gap:12px}.industry-visual{aspect-ratio:1.32;margin:8px 8px 0;border-radius:13px}.industry-card{border-radius:17px}.industry-card-content{padding:16px 13px 17px}.industry-card h3{font-size:16px}.industry-card p{font-size:11px;line-height:1.58;margin:8px 0 12px}.industry-tags{gap:5px}.industry-tags span{font-size:9px;padding:5px 7px;min-height:24px}.industry-symbol{width:25px;height:25px;flex-basis:25px;border-radius:8px}.industry-bottom{margin-top:23px;flex-direction:column;align-items:flex-start;padding:22px}.industry-bottom a{width:100%}}
        @media(max-width:370px){.industry-grid{grid-template-columns:1fr}.industry-visual{aspect-ratio:1.85}.industry-card-content{padding:18px 19px 20px}.industry-card h3{font-size:20px}.industry-card p{font-size:12px}.industry-tags span{font-size:10px}}
        @media(prefers-reduced-motion:reduce){.industry-card,.industry-bottom a{transition:none}.industry-card:hover,.industry-bottom a:hover{transform:none}}

    </style>
</head>
<body>
<a class="skip" href="#main">Skip to main content</a>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="{{ url('/') }}" aria-label="DhakaWala ERP home">
            <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span></span>
            <span class="brand-copy">DhakaWala <small>ERP PLATFORM</small></span>
        </a>
        <nav class="desktop-links" aria-label="Main navigation">
            <a href="#platform">Platform</a>
            <a href="#industries">Industries</a>
            <a href="#why-dhakawala">Why DhakaWala</a>
            <a href="#how-it-works">How it works</a>
            <a href="#questions">FAQs</a>
        </nav>
        <div class="nav-actions">
            <a class="nav-signin" href="#access">Workspace login</a>
            <a class="button button-dark" href="#contact">Let's talk
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
            <details class="mobile-menu">
                <summary aria-label="Open navigation menu"><span class="hamburger" aria-hidden="true"><i></i><i></i><i></i></span></summary>
                <nav class="mobile-dropdown" aria-label="Mobile navigation">
                    <a href="#platform">Platform</a><a href="#industries">Industries</a><a href="#why-dhakawala">Why DhakaWala</a>
                    <a href="#how-it-works">How it works</a><a href="#questions">FAQs</a><a href="#access">Workspace login</a>
                </nav>
            </details>
        </div>
    </div>
</header>
<main id="main">
    <section class="hero" aria-labelledby="hero-title">
        <div class="container hero-grid">
            <div class="hero-text">
                <span class="eyebrow"><span class="dot"></span> One platform. Many organizations.</span>
                <h1 id="hero-title">Less admin.<br>More <span class="underline">momentum.</span><br><em>Every day.</em></h1>
                <p class="hero-description">Meet the workspace that brings your people, attendance, approvals and operations together. Beautifully organized, thoughtfully flexible, and made for teams who mean business.</p>
                <div class="hero-buttons">
                    <a class="button button-dark" href="#platform">Explore the platform <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
                    <a class="button button-outline" href="#access">Workspace access <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg></a>
                </div>
                <div class="hero-note"><span class="tiny-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span> Designed for practices, growing businesses and modern teams</div>
            </div>
            <div class="preview-area" aria-label="Illustrative DhakaWala ERP workspace preview">
                <div class="preview-halo" aria-hidden="true"></div>
                <div class="preview-window">
                    <div class="preview-titlebar"><span class="browser-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="preview-caption">Workspace experience</span><svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a8afaa" stroke-width="1.7"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M9 9h6v6H9z"/></svg></div>
                    <div class="preview-content" aria-hidden="true">
                        <aside class="preview-rail">
                            <span class="rail-logo">d</span>
                            <span class="rail-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-7 9 7v10H3z"/><path d="M9 20v-7h6v7"/></svg></span>
                            <span class="rail-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg></span>
                            <span class="rail-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="m8 13 3 3 5-6"/></svg></span>
                            <span class="rail-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 20h18M6 17V9M12 17V4M18 17v-6"/></svg></span>
                            <span class="rail-bottom">DW</span>
                        </aside>
                        <div class="preview-dashboard">
                            <div class="dashboard-overline"><span>Overview / Home</span><span class="mini-live">Workspace ready</span></div>
                            <h2>Work, in one clear view.</h2>
                            <p class="desc">Your team's day, beautifully organized.</p>
                            <div class="mock-stats">
                                <div class="mock-stat"><div class="stat-label">Attendance</div><div class="stat-title"><i class="stat-mark"></i> On track</div></div>
                                <div class="mock-stat"><div class="stat-label">Approvals</div><div class="stat-title"><i class="stat-mark teal"></i> In review</div></div>
                                <div class="mock-stat"><div class="stat-label">Payroll</div><div class="stat-title"><i class="stat-mark blue"></i> Organized</div></div>
                            </div>
                            <div class="mock-panels">
                                <div class="mock-panel"><h3>Activity overview</h3><p>A more connected team</p><div class="bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><div class="bar-labels"><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span></div></div>
                                <div class="mock-panel"><h3>Review queue</h3><p>Stay on top of the details</p><div class="review-list"><div class="review-item"><span class="review-icon">L</span> Leave requests <small>Review</small></div><div class="review-item"><span class="review-icon">C</span> Claims <small>Open</small></div><div class="review-item"><span class="review-icon">T</span> Timesheets <small>Ready</small></div></div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="float-chip"><span class="chip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span><span><strong>Everything in its place</strong><small>One thoughtfully connected experience</small></span></div>
                <span class="showcase-note">Illustrative interface preview</span>
            </div>
        </div>
    </section>
    <section class="proof" aria-label="Platform highlights">
        <div class="container proof-grid">
            <div class="proof-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span>A platform for many teams</span></div>
            <div class="proof-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 11a3 3 0 1 0-1-6M19 20v-2a6 6 0 0 0-3-5"/></svg><span>Company-level brand control</span></div>
            <div class="proof-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3 4 7v5c0 5 3 8 8 9 5-1 8-4 8-9V7z"/><path d="m9 12 2 2 4-4"/></svg><span>Permission-aware by design</span></div>
        </div>
    </section>
    <section class="section" id="platform" aria-labelledby="features-title">
        <div class="container">
            <div class="section-heading">
                <span class="kicker">The platform</span>
                <h2 id="features-title">All your moving parts.<br>One beautiful place.</h2>
                <p>From the first check-in to the final approval, bring essential day-to-day work into a single, organized experience.</p>
            </div>
            <div class="feature-grid">
                <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/></svg></div><h3>Attendance & time</h3><p>Keep check-ins, schedules, timesheets and attendance reviews in one clear workflow.</p></article>
                <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="m8 12 3 3 5-6"/></svg></div><h3>Leave & approvals</h3><p>Move requests forward with defined roles, flexible approval paths and documented decisions.</p></article>
                <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 10h18M7 15h4"/></svg></div><h3>Conveyance claims</h3><p>Bring expense submissions, supporting receipts and reviewer decisions into one place.</p></article>
                <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 20h18M7 16V9M12 16V4M17 16v-6"/></svg></div><h3>Payroll workflows</h3><p>Organize payroll drafts, review steps and controlled approval processes with clarity.</p></article>
                <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 4V2M16 4V2M3 9h18M7 13h4M7 17h7"/></svg></div><h3>Engagements & work</h3><p>Keep projects, milestones and timesheets moving without scattered follow-ups.</p></article>
                <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 12h7M9 16h7"/></svg></div><h3>Notices & documents</h3><p>Share official updates and organize the documents your people need to stay informed.</p></article>
            </div>
        </div>
    </section>

    <section class="industry-section" id="industries" aria-labelledby="industry-title">
        <div class="container">
            <div class="industry-intro">
                <div class="industry-intro-main">
                    <span class="industry-overline">Industry flexibility</span>
                    <h2 id="industry-title">Built for the way <span>your organization</span> works.</h2>
                    <p>One thoughtfully adaptable ERP platform for different teams, structures and responsibilities. From partner-led practices to growing businesses, bring the right people and processes together.</p>
                </div>
                <div class="industry-intro-aside">Different structures. Different workflows. One refined workspace experience.</div>
            </div>
            <div class="industry-grid">
                <article class="industry-card">
                    <div class="industry-visual" role="img" aria-label="Professionals meeting around a conference table" style="--industry-photo:url('https://images.unsplash.com/photo-1542744173-8e7e53415bb0?auto=format&amp;fit=crop&amp;w=720&amp;q=76')"><span class="industry-index" aria-hidden="true">01</span></div>
                    <div class="industry-card-content">
                        <div class="industry-title-row"><h3>CA &amp; Audit Firms</h3><span class="industry-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V4h8v3M3 13h18M10 13v2h4v-2"/></svg></span></div>
                        <p>Purposeful workflows for partner-led practices and professional teams.</p>
                        <div class="industry-tags"><span>Partners</span><span>Articleship</span><span>Audit work</span><span>Payroll</span></div>
                    </div>
                </article>
                <article class="industry-card">
                    <div class="industry-visual" role="img" aria-label="Corporate colleagues collaborating at an office desk" style="--industry-photo:url('https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&amp;fit=crop&amp;w=720&amp;q=76')"><span class="industry-index" aria-hidden="true">02</span></div>
                    <div class="industry-card-content">
                        <div class="industry-title-row"><h3>Corporate Companies</h3><span class="industry-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M10 21v-3h4v3"/></svg></span></div>
                        <p>Connected people operations across departments, shifts and teams.</p>
                        <div class="industry-tags"><span>HR</span><span>Departments</span><span>Shifts</span><span>Approvals</span></div>
                    </div>
                </article>
                <article class="industry-card">
                    <div class="industry-visual" role="img" aria-label="Volunteer supporting a community event" style="--industry-photo:url('https://images.unsplash.com/photo-1559027615-cd4628902d4a?auto=format&amp;fit=crop&amp;w=720&amp;q=76')"><span class="industry-index" aria-hidden="true">03</span></div>
                    <div class="industry-card-content">
                        <div class="industry-title-row"><h3>NGOs &amp; Nonprofits</h3><span class="industry-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18M12 3c-5 5-5 13 0 18"/></svg></span></div>
                        <p>Clarity for project teams, field staff and program operations.</p>
                        <div class="industry-tags"><span>Projects</span><span>Field teams</span><span>Claims</span><span>Reports</span></div>
                    </div>
                </article>
                <article class="industry-card">
                    <div class="industry-visual" role="img" aria-label="Consultants planning work together at a meeting" style="--industry-photo:url('https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&amp;fit=crop&amp;w=720&amp;q=76')"><span class="industry-index" aria-hidden="true">04</span></div>
                    <div class="industry-card-content">
                        <div class="industry-title-row"><h3>Consultancy Firms</h3><span class="industry-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 20h16M6 16V9M12 16V4M18 16v-6"/></svg></span></div>
                        <p>A more organized home for assignments, time and shared work.</p>
                        <div class="industry-tags"><span>Engagements</span><span>Timesheets</span><span>Expenses</span><span>Teams</span></div>
                    </div>
                </article>
            </div>
            <div class="industry-bottom">
                <div>
                    <strong>Your structure is unique. Your ERP should respect that.</strong>
                    <p>Configurable roles, policies and approvals support a setup that fits the way your organization operates.</p>
                </div>
                <a href="mailto:support@dhakawala.com?subject=DhakaWala%20ERP%20Organization%20Setup">Discuss your organization
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
            </div>
        </div>
    </section>
    <section class="container" id="why-dhakawala" aria-labelledby="trust-title">
        <div class="dark-feature">
            <div>
                <span class="kicker">Thoughtfully built</span>
                <h2 id="trust-title">Flexible for teams.<br>Considered for leaders.</h2>
                <p>DhakaWala ERP is an independent subscription platform for modern organizations. Each company can have its own workspace, identity and governance—without changing the platform's core experience.</p>
                <a class="text-link" href="#access">Explore workspace access <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
            </div>
            <div class="safety-list">
                <div class="safety-item"><span class="safety-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3M7 13h4M7 17h7"/></svg></span><div><h3>Independent partner workspaces</h3><p>Support separate companies, independent Partner teams and their own approved brand identity.</p></div></div>
                <div class="safety-item"><span class="safety-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 11h5M19.5 8.5v5"/></svg></span><div><h3>Permissions that fit your structure</h3><p>Define responsibility for managers, reviewers and staff through controlled access.</p></div></div>
                <div class="safety-item"><span class="safety-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 4 7v5c0 5 3 8 8 9 5-1 8-4 8-9V7z"/><path d="m9 12 2 2 4-4"/></svg></span><div><h3>Security-minded account controls</h3><p>Role boundaries, verification options and activity records where they matter.</p></div></div>
            </div>
        </div>
    </section>
    <section class="section flow-section" id="how-it-works" aria-labelledby="flow-title">
        <div class="container">
            <div class="section-heading"><span class="kicker">Everyday simplicity</span><h2 id="flow-title">A better rhythm for<br>your working day.</h2><p>Less chasing. Less switching. More room to focus on the work in front of you.</p></div>
            <div class="flow-grid">
                <div class="flow-card"><span class="flow-num">01</span><h3>Bring your team together</h3><p>Set up company and workspace access for the people who belong in each team.</p></div>
                <div class="flow-card"><span class="flow-num">02</span><h3>Move work forward</h3><p>Capture attendance, requests, reviews and operational progress as the day unfolds.</p></div>
                <div class="flow-card"><span class="flow-num">03</span><h3>Stay confidently organized</h3><p>Follow decisions, reports and payroll review workflows without losing the context.</p></div>
            </div>
        </div>
    </section>
    <section class="access" id="access" aria-labelledby="access-title">
        <div class="container access-grid">
            <div><span class="kicker">Already part of a team?</span><h2 id="access-title">Your workspace.<br>Your own link.</h2><p>DhakaWala ERP uses private organization workspaces. If your company has already joined, use the workspace address shared by your administrator. Public self-registration is not available.</p></div>
            <div class="access-card">
                <div class="mini-title">Your organization URL</div>
                <div class="url-bar"><svg class="url-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span><strong>erp.dhakawala.com/</strong><em>your-company</em></span></div>
                <div class="access-buttons">
                    <a class="button button-dark" href="{{ route('erp.platform.login') }}">Platform administrator <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
                    <a class="button button-outline" href="mailto:support@dhakawala.com?subject=DhakaWala%20ERP%20Workspace%20Access">Need access?</a>
                </div>
                <p class="access-small">Workspace addresses are assigned by your organization. Administrator access is restricted to authorized platform users.</p>
            </div>
        </div>
    </section>
    <section class="faq-section" id="questions" aria-labelledby="faq-title">
        <div class="container faq-layout">
            <div><span class="kicker">Good to know</span><h2 id="faq-title">A few things<br>worth knowing.</h2><p class="intro">Thoughtful software should feel straightforward from the first conversation.</p></div>
            <div class="faq-list">
                <details><summary>Who is DhakaWala ERP designed for?</summary><p>It is built around the operational needs of CA firms, Partner-led practices and modern organizations managing people, attendance, approvals and shared workflows.</p></details>
                <details><summary>Can different Partners manage separate teams?</summary><p>Yes. The platform supports company and workspace structures, along with configurable access and approval controls for different responsibilities.</p></details>
                <details><summary>Can employees sign up themselves?</summary><p>There is no public self-registration. Workspace accounts are managed by authorized company administrators, so staff should obtain their login link from their organization.</p></details>
                <details><summary>Is DhakaWala ERP tied to one CA firm?</summary><p>No. DhakaWala ERP is an independent SaaS platform. Each subscribing organization can configure its own workspace, roles and permitted branding. RA&amp;C is one example of a firm that can use the service.</p></details>
                <details><summary>Can we choose monthly or annual billing?</summary><p>Monthly and annual subscription plans are part of the platform's commercial workflow. Contact our team to discuss the currently available plans and your organization's requirements.</p></details>
                <details><summary>Can we discuss a setup for our organization?</summary><p>Absolutely. Get in touch and tell us about your team structure, approval needs and operations. We can discuss the right next steps.</p></details>
            </div>
        </div>
    </section>
    <section class="final-cta" id="contact" aria-labelledby="cta-title">
        <div class="container cta-row">
            <div><h2 id="cta-title">Make room for better work.</h2><p>Discover what a more connected organization can feel like.</p></div>
            <a class="button button-dark" href="mailto:support@dhakawala.com?subject=DhakaWala%20ERP%20Enquiry">Talk to our team <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
        </div>
    </section>
</main>
<footer class="site-footer">
    <div class="container">
        <div class="footer-main">
            <div><a class="brand" href="{{ url('/') }}" aria-label="DhakaWala ERP home"><span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span></span><span class="brand-copy">DhakaWala <small>ERP PLATFORM</small></span></a><p class="footer-description">A thoughtful place for people, approvals and everything that keeps work moving.</p></div>
            <nav class="footer-links" aria-label="Footer navigation"><a href="#platform">Platform</a><a href="#industries">Industries</a><a href="#why-dhakawala">Why DhakaWala</a><a href="#access">Workspace access</a><a href="#questions">FAQs</a><a href="mailto:support@dhakawala.com?subject=DhakaWala%20ERP%20Enquiry">Contact</a></nav>
        </div>
        <div class="footer-bottom"><span>© {{ now()->year }} DhakaWala ERP. All rights reserved.</span><span class="footer-credit">Designed, engineered &amp; maintained by the <strong>DhakaWala Developer Team</strong></span></div>
    </div>
</footer>
</body>
</html>
