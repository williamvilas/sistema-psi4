<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum FunnelTagEnum: string
{
    use EnumTrait;

    case LANDING_PAGE = 'landing_page';
    case LANDING_PAGE_V3 = 'landing_page_v3';
    // Landing enxuta: sem cenas, Receita Saúde e calculadora
    case LANDING_PAGE_V4 = 'landing_page_v4';
    // Cliques nos CTAs da landing (um registro por visitante e CTA)
    case LANDING_CTA_REGISTER = 'landing_cta_register';
    case LANDING_CTA_SECURITY = 'landing_cta_security';
    case LANDING_CTA_VIDEO = 'landing_cta_video';
    case LOGIN = 'login';
    case REGISTER = 'register';
    case REQUEST_INVITE = 'request_invite';
    case FORGOT_PASSWORD = 'forgot_password';
    case DASHBOARD = 'dashboard';
    case PLANS = 'plans';
    case PAYMENT_OPTIONS = 'payment_options';
}
