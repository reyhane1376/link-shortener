# Link Shortener Project

## Postman Collection

A Postman collection is available for testing the system's APIs.  
You can download it using the link below:

[Download Postman Collection](https://raw.githubusercontent.com/reyhane1376/smart-library/refs/heads/main/postman/smart_book.postman_collection.json?token=GHSAT0AAAAAAC4LE5ZEKDFEE3P6GDWKFEIGZ6IJUCA)

This project is a URL shortening service implemented using **pure PHP**. The goal is to provide a simple and efficient system for shortening URLs and managing them via an API.

## Features
- **Authentication**: User registration and login using JWT.
- **Link Management**: Create, edit, delete, and view shortened links.
- **URL Shortening**: Generate short links using a hash_map.
- **Caching**: Redis is used to enhance performance.
- **Database**: PostgreSQL for data storage.

## Technologies
- **Programming Language**: Pure PHP (no framework)
- **Database**: PostgreSQL
- **Caching**: Redis
- **Dependency Management**: Composer with Autoloader
- **Short Link Generation**: Hash_map applied on the `short_code` column
- **Database Optimization**: Hash index (`hash_index USING HASH`) on the `short_code` column

### Setup Steps
1. **Clone the Repository**:
   ```bash
   git clone <repository-url>
   cd link-shortener
   ```

2. **Install Dependencies**:
   ```bash
   docker-compose up
   ```

3. **Environment Configuration**:
   You can modify the configuration by editing the config/config.php files in the project.

4. **Database Setup**:


## Short Code Length Formula
The length of the short code (`short_code`) depends on the number of links and the character set used. The formula to calculate the minimum required length is:

    Short code length = ceil(log(N, C))

Where:
- `N`: Number of links in the system (e.g., 1,000,000 links)
- `C`: Number of characters in the set (e.g., 62 if using `a-z, A-Z, 0-9`)
- `ceil`: Ceiling function (rounds up to the nearest integer)

### Example Calculation:
Assume 1 million links (`N = 1,000,000`) and a 62-character set (`C = 62`).
```math
log(1,000,000, 62) \approx 5.75
ceil(5.75) = 6
```
Result: The minimum `short_code` length should be **6** characters to support 1 million links.

In this project, a **hash_map** is used to generate unique short codes for each URL.

## API Endpoints
The API endpoints are documented in the provided Postman Collection. Below is a summary:

### Authentication
#### Register:
- `POST /api/v1/register`
- Body:
  ```json
  {"username": "testuser", "password": "Abcd@6378", "email": "test@example.com"}
  ```

#### Login:
- `POST /api/v1/login`
- Body:
  ```json
  {"username": "testuser", "password": "Abcd@6378", "email": "test@example.com"}
  ```

### Link Management
#### Create Link:
- `POST /api/v1/links`
- Authorization: Bearer `<JWT_TOKEN>`
- Body:
  ```json
  {"original_url": "https://example.com"}
  ```

#### Get a Link:
- `GET /api/v1/links/<id>`
- Authorization: Bearer `<JWT_TOKEN>`

#### Get All Links:
- `GET /api/v1/links`
- Authorization: Bearer `<JWT_TOKEN>`

#### Update Link:
- `PUT /api/v1/links/<id>`
- Authorization: Bearer `<JWT_TOKEN>`
- Body:
  ```json
  {"original_url": "https://new-example.com"}
  ```

#### Delete Link:
- `DELETE /api/v1/links/<id>`
- Authorization: Bearer `<JWT_TOKEN>`

#### Redirect:
- `GET /<short_code>`
- Authorization: Bearer `<JWT_TOKEN>`

## Project Structure
```
link-shortener/
├── config            # Environment configuration
├── routes            # routes
├── shemas            # shemas
├── public/           # Entry point (index.php)
├── src/              # Core project code
├── vendor/           # Composer dependencies
├── Dockerfile        #
├── docker-compose    # 
└── README.md         # Project documentation
```

## Notes
- Import the provided **Postman Collection** to test the APIs.
- **Redis** is used to cache frequently accessed links, reducing response time.

