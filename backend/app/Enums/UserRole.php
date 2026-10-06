<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';

    case FinanceStaff = 'finance_staff';

    case User = 'user';
}
