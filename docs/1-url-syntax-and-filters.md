# URL Syntax & Supported Filters

The package seamlessly supports two distinct formats for URL parameters. You can use whichever format best fits your frontend application.

### 1. JSON Syntax
You can pass raw JSON strings directly into the URL parameter. This is ideal for modern JavaScript frontends that easily serialize objects.

| Operation | Example URL |
| :-------- | :------ |
| **Filters** | `?filters={"name":{"like":"John"}}` |
| **Sorts** | `?sorts={"created_at":"desc"}` |
| **Fields** | `?fields=["id","name"]` |
| **Includes** | `?includes={"posts":{"fields":["id","title"]}}` |
| **Pagination**| `?page={"number":2,"limit":50}` |

### 2. Structured Array Syntax
The standard PHP/Laravel nested array syntax. This is ideal for traditional HTML forms or programmatic URL generation.

| Operation | Example URL |
| :-------- | :------ |
| **Filters** | `?filters[name][like]=John` |
| **Sorts** | `?sorts[created_at]=desc` |
| **Fields** | `?fields[]=id&fields[]=name` |
| **Includes** | `?includes[posts][fields]=id,title` |
| **Pagination**| `?page[number]=2&page[limit]=50` |

---

## Supported Filter Operators

| Operator | Description | URL Example (Array Syntax) | DB Support |
| :------- | :---------- | :---------- | :--------- |
| `or`, `and`, `not` | Logical grouping | `?filters[or][0][status][eq]=active` | Universal |
| `eq`, `ne` | Equal / Not Equal | `?filters[status][eq]=active` | Universal |
| `like`, `notlike` | Pattern matching | `?filters[name][like]=John` | Universal |
| `ilike`, `notilike` | Case-insensitive matching | `?filters[email][ilike]=HOTMAIL` | Universal (Graceful fallback) |
| `gt`, `gte` | Greater than (or equal) | `?filters[price][gt]=100` | Universal |
| `lt`, `lte` | Less than (or equal) | `?filters[age][lte]=18` | Universal |
| `in`, `nin` | In list / Not in list | `?filters[id][in]=1,2,3` | Universal |
| `null`, `notnull` | Null checks | `?filters[deleted_at][null]=true` | Universal |
| `between`, `nbetween` | Range queries | `?filters[price][between]=10,50` | Universal |
| `contains` | JSON/Array contains | `?filters[tags][contains]=urgent` | Universal |
| `exists`, `notexists` | Relationship existence | `?filters[posts][exists]=true` | Universal |
| `year`, `month`, `day` | Date component match | `?filters[created_at][year]=2024` | Universal |
| `date`, `time` | Exact date/time match | `?filters[created_at][date]=2024-01-01` | Universal |
| `containedby` | JSON array contained by | `?filters[tags][containedby]=["urgent"]` | PostgreSQL only |
| `overlap` | JSON array overlap | `?filters[tags][overlap]=["urgent"]` | PostgreSQL only |
| `fts` | Full-text search | `?filters[content][fts]=laravel` | Universal |

*(Note: PostgreSQL-specific operators securely abort with an `InvalidArgumentException` if executed on non-PostgreSQL engines to prevent raw SQL syntax errors).*
