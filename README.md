
# EduTrack Backend

EduTrack Backend is a Laravel-based RESTful API server for managing university curricula, courses, departments, concentrations, blacklists, constraints, and user roles (Chairperson, Faculty, etc.). It provides endpoints for curriculum management, course uploads, access control, and audit logging.

## Table of Contents
- [Features](#features)
- [Project Structure](#project-structure)
- [Setup & Installation](#setup--installation)
- [Environment Configuration](#environment-configuration)
- [Running the Application](#running-the-application)
- [API Overview](#api-overview)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Features
- Curriculum CRUD (Create, Read, Update, Delete)
- Course management (add/remove courses to curricula)
- Prerequisite and corequisite management
- Concentration and blacklist management
- Constraint and elective rule management
- CSV upload for bulk curriculum/course updates
- Role-based access control (Chairperson, Faculty, etc.)
- Audit logging for sensitive actions

## Project Structure

```
├── app/
│   ├── Http/Controllers/API/Chairperson/CurriculumController.php
│   ├── Models/
│   └── ...
├── config/
├── database/
├── routes/
│   ├── api.php
│   └── ...
├── tests/
├── public/
├── resources/
├── composer.json
├── package.json
└── README.md
```

## Setup & Installation

1. **Clone the repository:**
	```bash
	git clone https://github.com/your-org/edutrack-backend.git
	cd edutrack-backend
	```
2. **Install dependencies:**
	```bash
	composer install
	npm install
	```
3. **Copy and configure environment:**
	```bash
	cp .env.example .env
	# Edit .env as needed (DB, mail, etc.)
	```
4. **Generate application key:**
	```bash
	php artisan key:generate
	```
5. **Run migrations and seeders:**
	```bash
	php artisan migrate --seed
	```

## Running the Application

Start the local development server:

```bash
php artisan serve
```

## API Overview

The API is organized under `/api/`. Key endpoints include:

- `GET    /api/curriculum` — List all curricula
- `POST   /api/curriculum` — Create a new curriculum (Chairperson only)
- `GET    /api/curriculum/{id}` — Get curriculum details
- `PUT    /api/curriculum/{id}` — Update curriculum (Chairperson only)
- `DELETE /api/curriculum/{id}` — Delete curriculum (Chairperson only)
- `POST   /api/curriculum/upload` — Upload curriculum courses via CSV (Chairperson only)
- `GET    /api/curriculum/{id}/courses` — List courses in a curriculum
- `POST   /api/curriculum/{id}/courses` — Add course to curriculum
- `DELETE /api/curriculum/{id}/courses/{courseId}` — Remove course from curriculum
- ...and more for concentrations, blacklists, constraints, prerequisites, and corequisites.

See the `docs/` folder for detailed API documentation and usage examples.

## Testing

Run tests using PHPUnit:

```bash
php artisan test
```

## Contributing

1. Fork the repository
2. Create a new branch (`git checkout -b feature/your-feature`)
3. Commit your changes with clear messages
4. Push to your fork and submit a pull request

## License

This project is open-sourced under the [MIT license](https://opensource.org/licenses/MIT).
