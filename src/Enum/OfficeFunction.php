<?php

namespace App\Enum;

enum OfficeFunction: string
{
    case President = 'president';
    case VicePresident = 'vice_president';
    case Treasurer = 'tresorier';
    case Secretary = 'secretaire';
    case ActiveMember = 'membre_actif';
    case None = 'aucune';
}