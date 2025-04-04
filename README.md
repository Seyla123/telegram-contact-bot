# Telegram Data Collect Bot API

A Laravel-based backend API to collect and manage user contacts/messages from Telegram bots, designed for e-commerce or lead generation.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-8892BF.svg)](https://php.net/)

## Features

-   📞 **Contact Collection**: Saves Telegram user profiles (ID, name, phone, username)
-   💬 **Message Storage**: Logs all user-bot interactions
-   🔄 **Two-Way Sync**: Reply to users via API

## Quick Start

### Prerequisites

-   PHP 8.1+, Laravel 9+, MySQL/PostgreSQL
-   Telegram Bot Token ([@BotFather](https://t.me/BotFather))

### Installation

```bash
git clone https://github.com/yourusername/telegram-data-collect-bot.git
cd telegram-data-collect-bot
cp .env.example .env  # Configure DB and Telegram vars
composer install
php artisan migrate
```