# Lost Roleplay Shop - Project Map

## [TECH_STACK]
- **Backend:** PHP 8.x (vanilla, no framework)
- **Database:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3 (custom), Vanilla JS
- **Server:** Apache (with mod_rewrite) or Nginx
- **Auth:** Password hashing (bcrypt via `password_hash`/`password_verify`)
- **Styling:** RTL-optimized custom CSS with dark theme + Inwi brand colors (#e91e63)

## [SYSTEM_FLOW]
```
User (Browser)
  │
  ├── public/index.php      → Landing page + server status
  ├── public/shop.php       → Products priced in Coins
  ├── public/cart.php       → Cart view (localStorage-based)
  ├── public/checkout.php   → Pay with Coins (instant)
  ├── public/orders.php     → User order history (requires auth)
  ├── public/profile.php    → Profile, coins, buy coins (upload proof)
  ├── public/login.php      → Auth page
  │
  ├── api/deliver.php       → SAMP server auto-delivery endpoint
  │
  └── admin/
      ├── index.php         → Dashboard
      ├── products.php      → CRUD products (MAD price → auto coins)
      ├── orders.php        → Manage orders + deliveries
      ├── users.php         → Manage users + coins
      ├── coin_purchases.php → Confirm/reject coin purchases
      ├── applications.php  → Staff applications
      └── bundles.php       → Bundle discounts
```

### Coin Purchase Flow
1. User selects coin package in Profile → Add Funds
2. User uploads payment proof (screenshot) + selects payment method
3. Admin reviews in **Coin Purchases** → clicks Confirm
4. Coins auto-added to user + transaction logged
5. User can also send via WhatsApp (manual alternative)

### Product Purchase Flow (Coins)
1. All products priced in **Coins** (price × 10 dynamically)
2. User adds to cart → checkout → confirms with coins
3. Coins deducted instantly → order status = processing
4. Auto-creates **delivery** record in `deliveries` table
5. `GameDelivery` class writes directly to remote game DB (auto-delivery)
6. If game DB not connected, deliveries stay pending for admin retry
7. SAMP server can also call `api/deliver.php?action=pending` as fallback
8. Admin can click 🚀 Deliver Now on any order with pending deliveries

## [ARCHITECTURE]
```
lost-roleplay-shop/
├── public/                  # Customer-facing frontend
│   ├── index.php            # Landing page with hero + all products
│   ├── shop.php             # Category-filtered shop
│   ├── cart.php             # Shopping cart
│   ├── checkout.php         # Order submission
│   ├── orders.php           # User order tracking
│   ├── login.php            # Login page
│   ├── logout.php           # Logout handler
│   ├── includes/
│   │   ├── header.php       # Navbar + HTML head
│   │   └── footer.php       # Footer + scripts
│   └── assets/
│       ├── css/style.css    # All styles (Inwi-themed, RTL, dark)
│       ├── js/app.js        # Cart class (localStorage, DOM)
│       └── images/
├── admin/                   # Admin dashboard
│   ├── index.php            # Dashboard with stats
│   ├── products.php         # Product CRUD
│   ├── orders.php           # Order management
│   ├── users.php            # User management
│   └── includes/
│       ├── header.php       # Admin layout + sidebar
│       └── footer.php
├── core/                    # Shared business logic
│   ├── Database.php         # PDO singleton wrapper (local shop DB)
│   ├── Auth.php             # Authentication (login, logout, require)
│   ├── Session.php          # Session + flash messages
│   ├── Logger.php           # Async-ready file logger (DEBUG/INFO/WARN/ERROR)
│   ├── GameDelivery.php     # Auto-delivery → writes directly to remote game DB
│   └── ServerQuery.php      # SAMP server query (players, hostname, etc.)
├── config/                  # Configuration
│   ├── app.php              # App constants (name, currency, contact)
│   ├── database.php         # Local shop DB credentials (env-overridable)
│   └── game_database.php    # Remote game server DB credentials
├── sql/
│   └── schema.sql           # Full schema + seed data (categories, products, payment_methods, admin user)
├── api/                     # External API endpoints
│   └── deliver.php          # SAMP server auto-delivery (JSON)
├── logs/                    # Application logs (gitignored)
├── uploads/
│   ├── avatars/             # User avatar uploads
│   └── proofs/              # Coin purchase payment proofs
└── PROJECT_MAP.md           # This file
```

## [DATABASE SCHEMA]
10 tables: `categories`, `products`, `users`, `orders`, `order_items`, `payment_methods`,
`coin_transactions`, `coin_purchases`, `deliveries`, `staff_applications`, `bundles`, `bundle_products`
- Products pre-seeded with all pricing as specified (MAD in DB, coins calculated as `price × 10`)
- Payment methods: Inwi (default, primary), Cash Plus, Wafacash, CIH Bank
- Admin user: admin / password
- **coin_purchases**: stores user purchase requests with proof file upload
- **deliveries**: auto-created on coin order; SAMP server fetches via `api/deliver.php`

## [ORPHANS & PENDING]
- [x] **Payment proof upload** — ✅ profile.php upload + admin/coin_purchases.php confirm
- [x] **In-game API integration** — ✅ api/deliver.php + core/GameDelivery.php direct DB writes
- [x] **Auto-delivery on coin orders** — ✅ GameDelivery runs on checkout + admin order processing
- [x] **Deliver Now button** — ✅ admin can retry failed/pending deliveries manually
- [ ] **Remote game DB connection** — blocked: user's IP `196.206.124.233` needs GRANT on `45.8.187.109`
- [ ] **WhatsApp API integration** — auto-notify admin on new order/purchase
- [ ] **Email/OTP verification** — for account recovery
- [ ] **Pagination** — for large product/order lists
- [ ] **Multi-language** — toggle Arabic/French/English
- [ ] **Rate limiting** — prevent spam orders
- [ ] **Unit tests** — PHPUnit for core classes
