Modular/DDD Architect

src/
├── Shared/              # General utilities, common interfaces, base classes
├── BoundedContextA/     # e.g., src/Competition/
│   ├── Domain/          # Pure business logic, framework-agnostic
│   │   ├── Model/       # Entities, Aggregate Roots, Value Objects
│   │   ├── Repository/  # Interfaces for data access
│   │   ├── Service/     # Domain services
│   │   └── Event/       # Domain events
│   ├── Application/     # Use cases, orchestrates domain objects
│   │   ├── Command/     # Represents intent to perform an action
│   │   ├── Query/       # Represents a request for data
│   │   ├── Handler/     # Logic to process Commands/Queries
│   │   └── DTO/         # Data Transfer Objects for input/output
│   ├── Infrastructure/  # Framework-specific implementations, external concerns
│   │   ├── Persistence/ # Doctrine repositories (implementing Domain interfaces)
│   │   ├── Security/    # Symfony security components specific to this context
│   │   ├── Messenger/   # Messenger-related config/handlers
│   │   └── APIClient/   # Clients for external APIs
│   ├── Presentation/ (or UI/) # Web/API interface
│   │   ├── Controller/  # Symfony controllers (should be thin)
│   │   ├── Form/        # Symfony Form Types
│   │   └── Twig/        # Twig templates
│   └── Tests/           # Tests for this bounded context
│
├── BoundedContextB/     # e.g., src/PublicCompetition/
│   ├── ...
├── BoundedContextC/     # e.g., src/Submission/
│   ├── ...
└── Kernel.php
-------------------

src/
├── Shared/
│   ├── Domain/
│   │   ├── ValueObject/         # e.g., DateTimeRange, Uuid (if generic)
│   │   └── Util/                # Generic utilities
│   ├── Infrastructure/
│   │   ├── Doctrine/            # Custom Doctrine types, listeners applicable across contexts
│   │   └── Messenger/           # Generic message bus config/middleware
│   └── Presentation/            # Common Twig layouts/macros, base controllers
│
├── Security/                    # Symfony Security specific config (Voters, UserProvider, etc.)
│   ├── Domain/                  # (If you have specific security domain logic)
│   ├── Infrastructure/
│   └── Presentation/
│
├── Competition/                 # Bounded Context: Managing Competitions (Organizer Backoffice)
│   ├── Domain/
│   │   ├── Model/
│   │   │   └── Competition.php       # The Competition Aggregate Root
│   │   ├── Repository/
│   │   │   └── CompetitionRepositoryInterface.php # Interface
│   │   └── Service/
│   │       └── CompetitionCreator.php # Service for creating Competition
│   │       └── CompetitionUpdater.php # Service for updating Competition
│   │       └── CompetitionRemover.php # Service for deleting Competition
│   │   └── Event/
│   │       └── CompetitionCreatedEvent.php # Domain event
│   │       └── CompetitionUpdatedEvent.php
│   │       └── CompetitionDeletedEvent.php
│   │
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── CreateCompetitionCommand.php
│   │   │   ├── UpdateCompetitionCommand.php
│   │   │   └── DeleteCompetitionCommand.php
│   │   ├── Handler/
│   │   │   ├── CreateCompetitionHandler.php
│   │   │   ├── UpdateCompetitionHandler.php
│   │   │   └── DeleteCompetitionHandler.php
│   │   ├── Query/
│   │   │   └── GetCompetitionDetailsQuery.php # For fetching a single comp
│   │   │   └── ListCompetitionsQuery.php      # For listing comps
│   │   ├── DTO/
│   │   │   ├── CompetitionCreateDTO.php
│   │   │   ├── CompetitionUpdateDTO.php
│   │   │   └── CompetitionViewDTO.php         # DTO for presenting competition data
│   │   │
│   ├── Infrastructure/
│   │   ├── Persistence/
│   │   │   └── DoctrineCompetitionRepository.php # Doctrine implementation
│   │   │
│   │   ├── Form/                               # Forms specific to backend CRUD
│   │   │   └── CompetitionType.php
│   │   ├── Security/                           # Backend access control, e.g., CompetitionVoter
│   │   │   └── IsOrganizerVoter.php
│   │   ├── EventSubscriber/                    # Symfony event subscribers (e.g., logging)
│   │   └── Messenger/                          # Specific handlers for messenger
│   │
│   ├── Presentation/ (or UI/)
│   │   ├── Controller/
│   │   │   └── OrganizerCompetitionController.php # CRUD actions for organizers
│   │   ├── Twig/
│   │   │   └── competition/                      # Backend templates (e.g., list.html.twig, form.html.twig)
│   │   │
│   └── Tests/                                  # Tests for this context
│       ├── Domain/
│       ├── Application/
│       └── Infrastructure/
│       └── Presentation/
│
├── PublicCompetition/           # Bounded Context: Public Viewing of Competitions
│   ├── Domain/                  # (Might be minimal, focusing on read-only models if distinct)
│   │   └── Model/
│   │       └── PublicCompetitionView.php # Or just alias src/Competition/Domain/Model/Competition
│   │   └── ReadModel/           # Often used for CQRS read models
│   │       └── CompetitionListItem.php
│   │       └── CompetitionDetailItem.php
│   │
│   ├── Application/
│   │   ├── Query/
│   │   │   ├── GetPublicCompetitionDetailQuery.php
│   │   │   └── ListPublicCompetitionsQuery.php
│   │   ├── Handler/
│   │   │   ├── GetPublicCompetitionDetailHandler.php
│   │   │   └── ListPublicCompetitionsHandler.php
│   │   ├── DTO/
│   │   │   ├── PublicCompetitionListDTO.php
│   │   │   └── PublicCompetitionDetailDTO.php
│   │   │
│   ├── Infrastructure/
│   │   ├── Persistence/         # Could contain read-optimized queries/repositories
│   │   └── Security/            # Public access rules
│   │
│   ├── Presentation/
│   │   ├── Controller/
│   │   │   └── PublicCompetitionController.php # Shows list and detail page
│   │   ├── Twig/
│   │   │   └── public_competition/            # Public-facing templates
│   │   │       ├── list.html.twig
│   │   │       └── detail.html.twig
│   │   │
│   └── Tests/
│
├── Submission/                  # Bounded Context: Managing Submissions
│   ├── Domain/
│   │   ├── Model/
│   │   │   └── Submission.php        # Submission Aggregate Root
│   │   ├── Repository/
│   │   │   └── SubmissionRepositoryInterface.php
│   │   ├── Service/
│   │   │   └── SubmissionCreator.php # Service for handling new submissions
│   │   │   └── SubmissionValidator.php # For complex business validation
│   │   └── Event/
│   │       └── SubmissionCreatedEvent.php
│   │
│   ├── Application/
│   │   ├── Command/
│   │   │   └── CreateSubmissionCommand.php
│   │   ├── Handler/
│   │   │   └── CreateSubmissionHandler.php
│   │   ├── DTO/
│   │   │   └── SubmissionCreateDTO.php
│   │   │   └── SubmissionResultDTO.php
│   │   │
│   ├── Infrastructure/
│   │   ├── Persistence/
│   │   │   └── DoctrineSubmissionRepository.php
│   │   ├── Form/
│   │   │   └── SubmissionFormType.php
│   │   ├── EventSubscriber/
│   │   └── Security/           # Rules for who can submit (e.g., must be logged in)
│   │
│   ├── Presentation/
│   │   ├── Controller/
│   │   │   └── SubmissionController.php # Handles submission form
│   │   ├── Twig/
│   │   │   └── submission/
│   │   │       └── form.html.twig
│   │   │       └── success.html.twig
│   │   │
│   └── Tests/
│
└── Kernel.php                   # The main application kernel
