<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Enums;

enum AssociatedIndex: string
{
    case TYPES = 'types';
    case TYPE = 'type';
    case RULES = 'rules';
    case COLUMNS = 'columns';
    case NAME = 'name';
    case FILTERS = 'filters';
    case SORTS = 'sorts';
    case PAGE = 'page';
    case FIELDS = 'fields';
    case INCLUDES = 'includes';
    case RELATIONS = 'relations';
    case NUMBER = 'number';
    case LIMIT = 'limit';
    case COLLECTION = 'collection';
    case PER_PAGE = 'per_page';
    case CURRENT_PAGE = 'current_page';
    case FROM = 'from';
    case TO = 'to';
    case LAST_PAGE = 'last_page';
    case PREV_PAGE_URL = 'prev_page_url';
    case NEXT_PAGE_URL = 'next_page_url';
    case FIRST_PAGE_URL = 'first_page_url';
    case LAST_PAGE_URL = 'last_page_url';
    case TOTAL = 'total';
    case PATH = 'path';
    case LINKS = 'links';
    case URL = 'url';
    case LABEL = 'label';
    case ACTIVE = 'active';
    case TABLES = 'tables';
    case TABLE = 'table';
    case OFFSET = 'offset';
    case GROUP = 'group';
    case GROUPS = 'groups';
}
