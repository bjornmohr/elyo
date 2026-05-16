<?php

namespace App\Enums;

enum Role: string
{
    case ELYO_ADMIN = 'ELYO_ADMIN';
    case ELYO_SUPPORT = 'ELYO_SUPPORT';
    case COMPANY_OWNER = 'COMPANY_OWNER';
    case COMPANY_ADMIN = 'COMPANY_ADMIN';
    case COMPANY_MANAGER = 'COMPANY_MANAGER';
    case EMPLOYEE = 'EMPLOYEE';
    case PARTNER = 'PARTNER';

    public function isCompanyRole(): bool
    {
        return in_array($this, [
            self::COMPANY_OWNER,
            self::COMPANY_ADMIN,
            self::COMPANY_MANAGER,
            self::EMPLOYEE,
        ]);
    }

    public function isPlatformRole(): bool
    {
        return in_array($this, [
            self::ELYO_ADMIN,
            self::ELYO_SUPPORT,
        ]);
    }

    public static function companyPortalRoles(): array
    {
        return [self::COMPANY_OWNER, self::COMPANY_ADMIN, self::COMPANY_MANAGER];
    }

    public static function adminPortalRoles(): array
    {
        return [self::ELYO_ADMIN, self::ELYO_SUPPORT];
    }
}
