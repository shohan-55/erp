<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8f8f5">
    <meta name="description" content="DhakaWala ERP brings attendance, approvals, payroll workflows and practice operations into one thoughtfully designed workspace for modern teams.">
    <meta property="og:type" content="website">
    <meta property="og:title" content="DhakaWala ERP — Clarity for the work that matters">
    <meta property="og:description" content="A beautifully connected workspace for people, approvals and practice operations.">
    <meta property="og:url" content="{{ url('/') }}">
    <title>DhakaWala ERP — Clarity for the work that matters</title>
    {{-- This page deliberately has no Vite dependency: updating the Blade view
         cannot leave the separate cPanel public_html asset bundle out of sync. --}}
    <style>
        :root{color-scheme:light;--ink:#16191d;--muted:#647079;--gold:#fcdf02;--gold-deep:#e8c900;--paper:#f8f8f5;--line:#e6e8e4;--display:ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Arial,sans-serif}
        *,*::before,*::after{box-sizing:border-box}
        html{scroll-behavior:smooth;scroll-padding-top:96px}
        body{margin:0;background:var(--paper);color:var(--ink);font-family:var(--display);font-size:16px;-webkit-font-smoothing:antialiased}
        a{color:inherit;text-decoration:none}
        button,input,summary{font:inherit}
        a:focus-visible,summary:focus-visible{outline:3px solid #c49c00;outline-offset:5px;border-radius:5px}
        ::selection{background:var(--gold);color:var(--ink)}
        .container{width:min(1192px,calc(100% - 48px));margin-inline:auto}
        .skip{position:absolute;top:-60px;left:16px;background:var(--gold);padding:13px 20px;z-index:100;border-radius:9px;font-weight:800}
        .skip:focus{top:14px}
        .site-header{position:relative;z-index:20;border-bottom:1px solid rgba(27,32,34,.07);background:rgba(248,248,245,.98)}
        .nav{height:88px;display:flex;align-items:center;justify-content:space-between;gap:28px}
        .brand{display:inline-flex;align-items:center;gap:11px;flex-shrink:0}
        .brand-mark{display:grid;grid-template-columns:repeat(3,5px);align-items:end;justify-content:center;gap:3px;width:40px;height:40px;border-radius:13px;background:var(--gold);transform:rotate(-5deg);box-shadow:0 4px 14px rgba(196,173,0,.12)}
        .brand-mark span{display:block;width:5px;background:#17191c;border-radius:4px 4px 2px 2px}
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
        .button-dark{background:#1b1e21;color:#fff;box-shadow:0 9px 18px rgba(19,23,26,.08)}
        .button-dark:hover{background:#31363b;box-shadow:0 12px 24px rgba(19,23,26,.15)}
        .button-yellow{background:var(--gold);color:#17191d;border-color:var(--gold);box-shadow:0 12px 28px rgba(204,175,0,.19)}
        .button-yellow:hover{background:#ffe93b}
        .button-outline{border-color:#d9dcd9;background:#fff;color:#1d2428}
        .button-outline:hover{border-color:#b8bcb9;background:#fcfcfa}
        .mobile-menu{display:none}
        .hero{position:relative;isolation:isolate;overflow:hidden;padding:77px 0 84px;background:radial-gradient(ellipse 730px 540px at 98% 27%,rgba(252,223,2,.105),transparent 74%),radial-gradient(ellipse 520px 340px at -11% 90%,rgba(218,226,216,.19),transparent 75%),var(--paper)}
        .hero::before{position:absolute;z-index:-1;content:"";inset:0;opacity:.39;background-image:radial-gradient(#d9ddd7 .6px,transparent .6px);background-size:22px 22px;mask-image:linear-gradient(90deg,transparent 10%,#000 100%)}
        .hero-grid{display:grid;grid-template-columns:minmax(0,.99fr) minmax(0,1.01fr);gap:56px;align-items:center}
        .eyebrow{display:inline-flex;align-items:center;gap:9px;min-height:31px;padding:0 12px;background:#fff;border:1px solid #e8e9e3;border-radius:999px;font-size:10px;font-weight:850;letter-spacing:1.2px;text-transform:uppercase;color:#525c5e}
        .eyebrow .dot{height:7px;width:7px;background:#e4ca00;border-radius:50%;box-shadow:0 0 0 4px #fcf7c0}
        .hero h1{margin:26px 0 20px;font-size:clamp(45px,5.1vw,75px);line-height:1.055;letter-spacing:-.075em;font-weight:850;max-width:650px}
        .hero h1 em{font-style:normal;color:#919a98}
        .hero h1 .underline{position:relative;white-space:nowrap;color:#1a1d1e}
        .hero h1 .underline::after{content:"";position:absolute;z-index:-1;bottom:3px;left:1px;right:-4px;height:15px;background:var(--gold);transform:skew(-7deg)}
        .hero-description{max-width:483px;color:#5e696e;font-size:16px;line-height:1.86}
        .hero-buttons{display:flex;flex-wrap:wrap;gap:12px;margin-top:31px}
        .hero-note{display:flex;align-items:center;gap:10px;margin-top:28px;font-size:12px;color:#7a8588}
        .hero-note .tiny-icon{display:grid;place-items:center;height:25px;width:25px;background:#e8eee4;border-radius:50%;color:#55745d}
        .hero-note svg{width:13px;height:13px}
        .preview-area{position:relative;min-width:0;padding:22px 0 25px}
        .preview-halo{position:absolute;inset:9% 1% -2% 7%;background:radial-gradient(circle at 65% 42%,rgba(252,223,2,.34),rgba(242,223,54,.08) 51%,transparent 73%);filter:blur(22px);z-index:-1}
        .preview-window{position:relative;width:100%;border:1px solid #e2e6e3;overflow:hidden;border-radius:20px;background:#fff;box-shadow:0 36px 78px rgba(26,35,30,.105),0 2px 10px rgba(26,35,30,.025);transform:rotate(-1.2deg)}
        .preview-titlebar{height:47px;display:flex;align-items:center;justify-content:space-between;padding:0 19px;background:#fff;border-bottom:1px solid #edf0ec}
        .browser-dots{display:flex;gap:5px}.browser-dots i{height:8px;width:8px;border:1px solid #cfd5d0;background:#e6ebe8;border-radius:50%}.browser-dots i:first-child{background:var(--gold);border-color:var(--gold-deep)}
        .preview-caption{font-size:10px;font-weight:700;letter-spacing:.08em;color:#8a9495;text-transform:uppercase}
        .preview-content{display:grid;grid-template-columns:87px minmax(0,1fr);min-height:355px}
        .preview-rail{display:flex;flex-direction:column;align-items:center;gap:21px;background:#191c1f;padding:24px 10px}
        .rail-logo{width:28px;height:28px;background:var(--gold);border-radius:9px;display:grid;place-items:center;font-size:16px;font-weight:950}
        .rail-item{width:34px;height:34px;display:grid;place-items:center;color:#70777c;border-radius:9px}
        .rail-item svg{width:18px;height:18px;stroke:currentColor}
        .rail-item.active{background:#333530;color:var(--gold)}
        .rail-bottom{margin-top:auto;width:26px;height:26px;border-radius:50%;background:#697475;color:white;display:grid;place-items:center;font-weight:800;font-size:11px}
        .preview-dashboard{min-width:0;padding:25px 23px;background:#f9faf8}
        .dashboard-overline{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#939d9e}
        .mini-live{display:flex;align-items:center;gap:5px;color:#3d8359;font-size:9px;font-weight:800;letter-spacing:0;text-transform:none}.mini-live::before{content:"";width:5px;height:5px;background:#48a870;border-radius:50%}
        .preview-dashboard h2{font-size:22px;letter-spacing:-.8px;margin:12px 0 4px;line-height:1.2}
        .preview-dashboard>.desc{font-size:10px;color:#98a0a0;margin:0 0 21px}
        .mock-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
        .mock-stat{background:#fff;border:1px solid #eff0eb;border-radius:11px;padding:13px 11px 12px;min-width:0}
        .stat-label{font-size:9px;color:#8b9493;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .stat-title{font-size:12px;font-weight:900;margin-top:9px;letter-spacing:-.3px}
        .stat-mark{display:inline-block;vertical-align:middle;width:7px;height:7px;border-radius:50%;background:#58a67b;margin-right:4px}.stat-mark.gold{background:#e5bf23}.stat-mark.blue{background:#88a3b7}
        .mock-panels{display:grid;grid-template-columns:1.12fr .88fr;gap:10px;margin-top:11px}
        .mock-panel{min-width:0;background:#fff;border:1px solid #eff0eb;border-radius:12px;padding:15px 13px}
        .mock-panel h3{font-size:10px;font-weight:850;margin:0;letter-spacing:-.2px}
        .mock-panel p{font-size:8px;color:#9ba3a2;margin:5px 0 0}
        .bars{height:89px;display:flex;align-items:flex-end;justify-content:space-between;gap:8px;margin-top:12px;border-bottom:1px solid #ecf0ec;background:repeating-linear-gradient(to top,#edf0eb 0,#edf0eb 1px,transparent 1px,transparent 26px)}
        .bars i{display:block;flex:1;max-width:25px;border-radius:5px 5px 1px 1px;background:#e9eddf}.bars i:nth-child(1){height:32%}.bars i:nth-child(2){height:58%}.bars i:nth-child(3){height:45%;background:#f5e57a}.bars i:nth-child(4){height:79%;background:var(--gold)}.bars i:nth-child(5){height:62%;background:#e5eec2}.bars i:nth-child(6){height:92%;background:#d1e4c0}.bars i:nth-child(7){height:75%;background:#fcdf02}
        .bar-labels{display:flex;justify-content:space-between;font-size:7px;color:#a3abaa;padding-top:7px}
        .review-list{margin-top:13px;display:grid;gap:12px}.review-item{display:flex;align-items:center;gap:8px;font-size:8px;font-weight:750;color:#667075}
        .review-icon{display:grid;place-items:center;flex:0 0 22px;width:22px;height:22px;background:#f4f6ef;border-radius:7px;font-weight:800;color:#738175;font-size:9px}
        .review-item small{margin-left:auto;background:#f4f5eb;color:#75804b;font-weight:800;padding:4px 6px;border-radius:5px;font-size:7px}
        .float-chip{position:absolute;bottom:6px;left:-27px;display:flex;align-items:center;gap:12px;padding:12px 15px;border:1px solid #e5e9e4;border-radius:13px;background:white;box-shadow:0 17px 30px rgba(33,40,31,.1);transform:rotate(2deg)}
        .float-chip .chip-icon{width:32px;height:32px;display:grid;place-items:center;background:#eff4ea;border-radius:9px;color:#43805b}
        .float-chip strong{display:block;font-size:11px}.float-chip small{display:block;color:#9da6a3;font-size:9px;margin-top:3px}
        .float-chip svg{width:17px;height:17px}
        .showcase-note{position:absolute;right:8px;bottom:0;color:#909a95;font-size:10px}
        .proof{border-block:1px solid #e9eae4;background:#fff}
        .proof-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;padding-block:25px}
        .proof-item{display:flex;align-items:center;justify-content:center;gap:12px}
        .proof-item svg{width:20px;height:20px;color:#737d77;stroke-width:1.7}
        .proof-item span{font-size:12px;font-weight:750;color:#636d6b;letter-spacing:-.12px}
        .section{padding:107px 0}
        .section-heading{max-width:600px;margin-inline:auto;text-align:center}
        .kicker{font-size:11px;font-weight:850;letter-spacing:1.6px;text-transform:uppercase;color:#857500}
        .section h2,.access h2,.dark-feature h2{font-size:clamp(34px,3.8vw,53px);line-height:1.12;letter-spacing:-.056em;font-weight:850;margin:16px 0}
        .section-heading p{margin:0;color:#6d7678;font-size:15px;line-height:1.8}
        .feature-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:45px}
        .feature-card{min-height:215px;padding:26px;border:1px solid #e8eae5;border-radius:19px;background:#fff;transition:transform .22s,border-color .22s,box-shadow .22s}
        .feature-card:hover{transform:translateY(-4px);border-color:#e6dfb0;box-shadow:0 20px 44px rgba(32,35,26,.05)}
        .feature-icon{width:44px;height:44px;border-radius:13px;background:#f7f6ee;display:grid;place-items:center;color:#2a3331}
        .feature-icon svg{width:21px;height:21px;stroke-width:1.7}
        .feature-card h3{font-size:18px;line-height:1.3;letter-spacing:-.55px;margin:23px 0 9px}
        .feature-card p{font-size:13px;line-height:1.8;margin:0;color:#778081}
        .dark-feature{position:relative;isolation:isolate;overflow:hidden;margin:0 auto;border-radius:28px;background:#1b1e21;color:#fff;padding:66px 70px;display:grid;grid-template-columns:.95fr 1.05fr;gap:70px}
        .dark-feature::before{content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(circle at 91% 17%,rgba(252,223,2,.14),transparent 42%),radial-gradient(circle at 8% 94%,rgba(255,255,255,.035),transparent 45%)}
        .dark-feature .kicker{color:#f1db50}
        .dark-feature h2{max-width:460px;font-size:clamp(34px,3.5vw,48px)}
        .dark-feature p{font-size:14px;color:#aeb6b6;line-height:1.9;max-width:440px}
        .dark-feature .text-link{display:inline-flex;align-items:center;gap:10px;margin-top:20px;font-weight:800;font-size:13px;color:#f3df55}
        .safety-list{display:grid;gap:11px}
        .safety-item{display:flex;align-items:flex-start;gap:15px;border:1px solid rgba(255,255,255,.1);border-radius:13px;background:rgba(255,255,255,.035);padding:17px 19px}
        .safety-icon{flex:0 0 33px;width:33px;height:33px;border:1px solid rgba(252,223,2,.3);background:rgba(252,223,2,.075);border-radius:9px;display:grid;place-items:center;color:#f0d844}
        .safety-icon svg{width:17px;height:17px}
        .safety-item h3{font-size:13px;margin:1px 0 5px}
        .safety-item p{font-size:11px;margin:0;line-height:1.6;color:#a8b1b2}
        .flow-section{padding:108px 0 100px}
        .flow-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:32px;margin-top:49px}
        .flow-card{position:relative;border-top:1px solid #dfe3df;padding-top:25px}
        .flow-card:not(:last-child)::after{content:"";position:absolute;top:-3px;right:-16px;width:6px;height:6px;border-radius:50%;background:#b9c2b9}
        .flow-num{display:inline-flex;align-items:center;justify-content:center;height:36px;min-width:36px;padding:0 8px;border:1px solid #e6e7db;background:#fff9ca;border-radius:11px;font-size:12px;font-weight:900}
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
        .final-cta{background:#fcdf02;padding:63px 0}
        .cta-row{display:flex;align-items:center;justify-content:space-between;gap:30px}
        .cta-row h2{margin:0;font-size:clamp(30px,3.5vw,46px);letter-spacing:-.05em;line-height:1.12}
        .cta-row p{margin:10px 0 0;font-size:13px;color:#6e6100}
        .site-footer{padding:43px 0 28px;background:#171a1c;color:#e8ebeb}
        .footer-main{display:flex;align-items:flex-start;justify-content:space-between;gap:35px}
        .footer-main .brand-mark{width:35px;height:35px;border-radius:10px}.footer-main .brand-copy{font-size:19px;color:#fff}
        .footer-main .brand-copy small{color:#7f8887}
        .footer-description{font-size:12px;line-height:1.8;color:#899392;max-width:335px;margin:18px 0 0}
        .footer-links{display:flex;flex-wrap:wrap;gap:25px;padding-top:13px}.footer-links a{font-size:12px;color:#aeb5b3;font-weight:650}
        .footer-bottom{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-top:41px;padding-top:22px;border-top:1px solid #303639;font-size:11px;color:#798583}
        @media(max-width:1080px){.hero-grid{gap:28px}.hero h1{font-size:clamp(43px,5.1vw,65px)}.dark-feature{gap:30px;padding:52px}.desktop-links{gap:17px}.nav{gap:16px}}
        @media(max-width:900px){.nav{height:76px}.desktop-links{display:none}.mobile-menu{display:block;position:relative}.mobile-menu>summary{display:grid;place-items:center;width:43px;height:43px;border:1px solid #dde1dc;border-radius:10px;cursor:pointer;list-style:none;background:#fff}.mobile-menu>summary::-webkit-details-marker{display:none}.hamburger{width:17px;height:15px;display:flex;flex-direction:column;justify-content:space-between}.hamburger i{height:2px;width:100%;background:#1b2021;border-radius:2px}.mobile-menu[open] .mobile-dropdown{position:absolute;right:0;top:55px;width:min(270px,calc(100vw - 48px));display:grid;gap:4px;padding:11px;border:1px solid #e1e5df;border-radius:14px;background:#fff;box-shadow:0 20px 45px rgba(31,38,31,.14)}.mobile-dropdown a{padding:11px 12px;border-radius:8px;font-size:13px;font-weight:650}.mobile-dropdown a:hover{background:#f7f8f4}.hero{padding:67px 0 68px}.hero-grid{grid-template-columns:1fr;gap:41px}.hero-text{max-width:650px}.hero h1{max-width:620px}.preview-area{width:min(620px,95%);margin-inline:auto}.feature-grid{grid-template-columns:repeat(2,1fr)}.dark-feature{grid-template-columns:1fr;gap:26px}.dark-feature h2{max-width:650px}.access-grid{grid-template-columns:1fr;gap:26px}.faq-layout{grid-template-columns:1fr;gap:32px}.faq-layout .intro{max-width:530px}}
        @media(max-width:600px){.container{width:min(100% - 36px,1192px)}.nav{height:70px}.brand-mark{width:36px;height:36px}.brand-copy{font-size:18px}.nav-signin{display:none}.nav-actions>.button{min-height:39px;padding:0 12px;font-size:11px}.hero{padding:59px 0 65px}.hero h1{font-size:clamp(42px,10.1vw,60px);letter-spacing:-.068em;margin:22px 0 16px}.hero-description{font-size:14px;line-height:1.8}.hero-buttons{gap:10px}.hero-buttons .button{flex:1;min-width:132px}.hero-note{font-size:11px}.preview-area{width:100%;padding:9px 0 26px}.preview-window{border-radius:14px;transform:none}.preview-content{grid-template-columns:48px minmax(0,1fr);min-height:300px}.preview-rail{padding:19px 4px;gap:18px}.rail-logo{height:25px;width:25px}.rail-item{width:27px;height:27px}.rail-item svg{width:15px;height:15px}.preview-dashboard{padding:18px 12px}.preview-dashboard h2{font-size:17px}.mock-stats{gap:5px}.mock-stat{padding:11px 7px}.stat-title{font-size:10px}.stat-label{font-size:8px}.mock-panels{gap:6px}.mock-panel{padding:11px 7px}.review-list{gap:10px}.review-item{font-size:7px;gap:3px}.review-icon{flex-basis:17px;width:17px;height:17px}.review-item small{font-size:6px;padding:3px}.float-chip{left:4px;bottom:3px;padding:9px}.showcase-note{right:0;font-size:9px}.proof-grid{grid-template-columns:1fr;gap:17px;padding-block:24px}.proof-item{justify-content:flex-start;padding-left:10px}.section{padding:77px 0}.section h2,.access h2,.dark-feature h2{font-size:clamp(33px,8vw,42px)}.section-heading p{font-size:14px}.feature-grid{grid-template-columns:1fr;gap:11px;margin-top:29px}.feature-card{min-height:0;padding:22px}.feature-card h3{margin-top:17px}.dark-feature{padding:36px 25px;border-radius:20px}.flow-section{padding:76px 0}.flow-grid{grid-template-columns:1fr;gap:29px;margin-top:36px}.flow-card:not(:last-child)::after{display:none}.access-grid{padding-block:73px}.access-card{padding:21px}.faq-section{padding:73px 0}.faq-list summary{font-size:14px}.cta-row{align-items:flex-start;flex-direction:column}.final-cta{padding:53px 0}.footer-main{flex-direction:column}.footer-bottom{flex-direction:column;align-items:flex-start}.footer-links{gap:17px}}
        @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.button,.feature-card{transition:none}.button:hover,.feature-card:hover{transform:none}}
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
                    <a href="#platform">Platform</a><a href="#why-dhakawala">Why DhakaWala</a>
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
                <span class="eyebrow"><span class="dot"></span> A better way to work together</span>
                <h1 id="hero-title">Less admin.<br>More <span class="underline">momentum.</span><br><em>Every day.</em></h1>
                <p class="hero-description">Meet the workspace that brings your people, attendance, approvals and operations together. Beautifully organized, thoughtfully flexible, and made for teams who mean business.</p>
                <div class="hero-buttons">
                    <a class="button button-dark" href="#contact">Explore DhakaWala ERP <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
                    <a class="button button-outline" href="#platform">Discover the platform <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg></a>
                </div>
                <div class="hero-note"><span class="tiny-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span> Designed for CA practices and modern organizations</div>
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
                                <div class="mock-stat"><div class="stat-label">Approvals</div><div class="stat-title"><i class="stat-mark gold"></i> In review</div></div>
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
            <div class="proof-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span>One connected workspace</span></div>
            <div class="proof-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 11a3 3 0 1 0-1-6M19 20v-2a6 6 0 0 0-3-5"/></svg><span>Flexible team governance</span></div>
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
    <section class="container" id="why-dhakawala" aria-labelledby="trust-title">
        <div class="dark-feature">
            <div>
                <span class="kicker">Thoughtfully built</span>
                <h2 id="trust-title">Flexible for teams.<br>Considered for leaders.</h2>
                <p>Every organization works a little differently. DhakaWala ERP is designed around clear responsibilities, configurable access and the freedom to organize people without compromising oversight.</p>
                <a class="text-link" href="#access">Explore workspace access <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
            </div>
            <div class="safety-list">
                <div class="safety-item"><span class="safety-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3M7 13h4M7 17h7"/></svg></span><div><h3>Independent partner workspaces</h3><p>Support distinct teams and Partner-led workflows inside the same organization.</p></div></div>
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
            <nav class="footer-links" aria-label="Footer navigation"><a href="#platform">Platform</a><a href="#why-dhakawala">Why DhakaWala</a><a href="#access">Workspace access</a><a href="#questions">FAQs</a><a href="mailto:support@dhakawala.com?subject=DhakaWala%20ERP%20Enquiry">Contact</a></nav>
        </div>
        <div class="footer-bottom"><span>© {{ now()->year }} DhakaWala ERP. All rights reserved.</span><span>Made for work that matters.</span></div>
    </div>
</footer>
</body>
</html>
