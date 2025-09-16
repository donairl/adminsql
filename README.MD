# CloneSQL

A web-based PostgreSQL database management tool built with PHP. CloneSQL provides an intuitive interface for managing PostgreSQL databases, executing SQL queries, and performing common database operations.

## Features

- **Database Connection Management**: Secure login with PostgreSQL credentials
- **Database Browser**: View and switch between multiple databases
- **Table Management**: 
  - Browse table structures and data
  - Create new tables with custom schemas
  - Drop existing tables
- **Data Operations**:
  - Insert new records with form-based interface
  - Delete multiple records with confirmation
  - Paginated data browsing (25 records per page)
- **SQL Query Interface**: Execute custom SQL queries with syntax highlighting
- **Session Management**: Secure authentication with session-based login/logout

## Requirements

- PHP 7.4 or higher
- PostgreSQL server
- Web server (Apache/Nginx)
- PHP PostgreSQL extension (`php-pgsql`)

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd clonesql
```

2. Configure your web server to serve the project directory

3. Ensure PHP PostgreSQL extension is installed:
```bash
# Ubuntu/Debian
sudo apt-get install php-pgsql

# CentOS/RHEL
sudo yum install php-pgsql
```

4. Access the application through your web browser at `http://your-domain/clonesql`

## Usage

### Initial Setup

1. Navigate to the application URL
2. Enter your PostgreSQL connection details:
   - **Host**: Database server address (default: localhost)
   - **Port**: Database port (default: 5432)
   - **Database**: Initial database to connect to (default: postgres)
   - **Username**: PostgreSQL username
   - **Password**: PostgreSQL password

### Main Features

#### Database Navigation
- Select databases from the dropdown menu
- Switch between databases seamlessly
- View all available tables in the selected database

#### Table Operations
- **Browse Data**: Click on any table to view its contents
- **View Structure**: See column definitions, data types, and constraints
- **Create Table**: Use the "Create Table" form to add new tables
- **Drop Table**: Remove tables with confirmation

#### Data Management
- **Insert Records**: Use the structured form to add new data
- **Delete Records**: Select and delete multiple rows
- **Pagination**: Navigate through large datasets (25 records per page)

#### SQL Interface
- Execute custom SQL queries
- View query results in tabular format
- Error handling for invalid queries

## File Structure

```
clonesql/
├── index.php              # Login page entry point
├── main.php               # Main dashboard entry point
├── logout.php             # Session termination
├── src/
│   ├── login.php          # Login controller and authentication
│   ├── main.php           # Main application controller
│   └── pgsql.php          # PostgreSQL database connection class
└── tools/
    ├── merge.php          # Utility tools
    └── merged.min.php     # Minified utilities
```

## Security Considerations

- Session-based authentication
- SQL injection protection through prepared statements
- Password storage in session (consider encryption for production)
- Input validation and sanitization

## Development

The application uses object-oriented PHP with namespaced classes:

- `App\Login`: Handles authentication and connection setup
- `App\Main`: Main application controller for database operations
- `App\Pgsql`: PostgreSQL connection and query management

## Browser Support

- Modern browsers with JavaScript enabled
- Responsive design for various screen sizes

## License

[Add your license information here]

## Contributing

[Add contribution guidelines here]

## Support

[Add support information here]
