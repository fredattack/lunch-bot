# Action Pattern in Laravel

## Concept

Le **Action Pattern** est une approche architecturale qui encapsule une unité unique de logique métier dans une classe dédiée. Chaque Action possède généralement une méthode publique `handle()` et utilise l'injection de dépendances via le constructeur.

Ce pattern permet de garder les controllers, commands et jobs focalisés sur leur rôle de coordination, tout en favorisant la réutilisabilité et la testabilité du code.

```php
namespace App\Actions;

class CreateUser
{
    public function handle(array $data): User
    {
        return User::create($data);
    }
}
```

## Avantages

### 1. Réutilisabilité Universelle

Une Action peut être invoquée depuis n'importe quel contexte :
- Controllers HTTP
- Jobs en queue
- Commandes Artisan
- Event Listeners
- Tests
- Autres Actions

```php
// Dans un Controller
public function store(CreateUserRequest $request, CreateUser $action): JsonResponse
{
    $user = $action->handle($request->validated());

    return response()->json($user, 201);
}

// Dans une Commande Artisan
public function handle(CreateUser $action): int
{
    $action->handle([
        'name' => $this->argument('name'),
        'email' => $this->argument('email'),
    ]);

    return Command::SUCCESS;
}

// Dans un Job
public function handle(CreateUser $action): void
{
    $action->handle($this->userData);
}
```

### 2. Dépendances Focalisées

Contrairement aux classes Service qui accumulent des méthodes sans rapport, chaque Action ne reçoit que les dépendances dont elle a besoin. Cela évite les constructeurs surchargés avec des injections inutilisées.

```php
// Mauvais : Service avec dépendances multiples non liées
class UserService
{
    public function __construct(
        private Mailer $mailer,
        private PaymentGateway $payment,
        private NotificationService $notifications,
        private ReportGenerator $reports
    ) {}

    public function createUser(array $data): User { /* ... */ }
    public function sendWelcomeEmail(User $user): void { /* ... */ }
    public function processPayment(User $user): void { /* ... */ }
}

// Bon : Action avec dépendances ciblées
class CreateUser
{
    public function __construct(
        private SendWelcomeEmail $sendWelcomeEmail
    ) {}

    public function handle(array $data): User
    {
        $user = User::create($data);
        $this->sendWelcomeEmail->handle($user);

        return $user;
    }
}
```

### 3. Tests Isolés

Les Actions encapsulent une responsabilité unique, ce qui les rend simples à tester indépendamment des couches HTTP ou des services externes.

```php
class CreateUserTest extends TestCase
{
    public function test_it_creates_a_user(): void
    {
        $action = new CreateUser();

        $user = $action->handle([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }
}
```

## Best Practices

### Structure des Dossiers

Placer les Actions dans `app/Actions` pour faciliter la découverte. Organiser par domaine si nécessaire :

```
app/
└── Actions/
    ├── Users/
    │   ├── CreateUser.php
    │   ├── UpdateUser.php
    │   └── DeleteUser.php
    ├── Orders/
    │   ├── CreateOrder.php
    │   ├── CancelOrder.php
    │   └── RefundOrder.php
    └── Notifications/
        └── SendOrderConfirmation.php
```

### Convention de Nommage

Utiliser le pattern `{Verbe}{Ressource}.php` :

| Action | Nom de fichier |
|--------|----------------|
| Créer un utilisateur | `CreateUser.php` |
| Mettre à jour une commande | `UpdateOrder.php` |
| Envoyer une notification | `SendNotification.php` |
| Synchroniser les rôles | `SyncUserRoles.php` |
| Calculer le total | `CalculateOrderTotal.php` |

### Méthode `handle()`

Utiliser `handle()` comme nom de méthode pour la cohérence avec les Jobs et Listeners de Laravel :

```php
class UpdateUser
{
    public function handle(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }
}
```

### Signature des Paramètres

- **Création** : passer uniquement les données (`array $data`)
- **Mise à jour** : passer la ressource puis les données (`Model $model, array $data`)
- **Suppression** : passer uniquement la ressource (`Model $model`)

```php
// Création
public function handle(array $data): User

// Mise à jour
public function handle(User $user, array $data): User

// Suppression
public function handle(User $user): void
```

### Transactions Database

Envelopper la logique multi-opérations dans `DB::transaction()` :

```php
class CreateOrderWithItems
{
    public function __construct(
        private CreateOrderItem $createOrderItem,
        private CalculateOrderTotal $calculateTotal
    ) {}

    public function handle(array $orderData, array $items): Order
    {
        return DB::transaction(function () use ($orderData, $items) {
            $order = Order::create($orderData);

            foreach ($items as $item) {
                $this->createOrderItem->handle($order, $item);
            }

            $this->calculateTotal->handle($order);

            return $order->fresh(['items']);
        });
    }
}
```

### Events

Dispatcher des événements quand une Action modifie des ressources :

```php
class CreateUser
{
    public function handle(array $data): User
    {
        $user = User::create($data);

        event(new UserCreated($user));

        return $user;
    }
}
```

### Valeurs de Retour

Toujours retourner la ressource créée ou modifiée :

```php
public function handle(array $data): User
{
    return User::create($data);
}

public function handle(User $user, array $data): User
{
    $user->update($data);

    return $user->fresh();
}
```

### Composition d'Actions

Injecter d'autres Actions pour construire des workflows complexes :

```php
class RegisterUser
{
    public function __construct(
        private CreateUser $createUser,
        private AssignDefaultRole $assignDefaultRole,
        private SendWelcomeEmail $sendWelcomeEmail,
        private CreateUserSettings $createUserSettings
    ) {}

    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->createUser->handle($data);
            $this->assignDefaultRole->handle($user);
            $this->createUserSettings->handle($user);
            $this->sendWelcomeEmail->handle($user);

            return $user;
        });
    }
}
```

## Quand Utiliser le Action Pattern

### Utiliser les Actions pour :

- Logique métier réutilisable dans plusieurs contextes
- Opérations complexes impliquant plusieurs modèles
- Code qui doit être facilement testable
- Workflows composés de plusieurs étapes

### Éviter les Actions pour :

- Opérations CRUD simples (utiliser directement le modèle)
- Logique spécifique à un seul controller sans réutilisation prévue
- Transformations de données simples (utiliser des Accessors/Mutators)

## Exemple Complet

```php
namespace App\Actions\Orders;

use App\Actions\Payments\ProcessPayment;
use App\Actions\Inventory\ReserveStock;
use App\Actions\Notifications\SendOrderConfirmation;
use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlaceOrder
{
    public function __construct(
        private ProcessPayment $processPayment,
        private ReserveStock $reserveStock,
        private SendOrderConfirmation $sendConfirmation
    ) {}

    public function handle(User $user, array $orderData, array $items): Order
    {
        return DB::transaction(function () use ($user, $orderData, $items) {
            $order = $user->orders()->create([
                'status' => 'pending',
                'total' => $this->calculateTotal($items),
                ...$orderData,
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
                $this->reserveStock->handle($item['product_id'], $item['quantity']);
            }

            $this->processPayment->handle($order);

            $order->update(['status' => 'confirmed']);

            event(new OrderPlaced($order));

            $this->sendConfirmation->handle($order);

            return $order->fresh(['items']);
        });
    }

    private function calculateTotal(array $items): int
    {
        return collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']);
    }
}
```

## Références

- [Action Pattern in Laravel - Nabil Hassen](https://nabilhassen.com/action-pattern-in-laravel-concept-benefits-best-practices)