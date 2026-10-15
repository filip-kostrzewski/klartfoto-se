<?php

namespace App;

enum CompanyRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
