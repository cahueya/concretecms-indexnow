# IndexNow Integration for ConcreteCMS

This package adds [IndexNow](https://www.indexnow.org/) support to ConcreteCMS (v9+), allowing instant URL submission to search engines when pages are published or modified.

## 🚀 Features

- Automatically notifies IndexNow-compatible search engines on page publish
- Optional manual task to submit all existing public pages
- Configurable API endpoint and key via Dashboard
- CLI task support for cron jobs or manual runs

---

## 📦 Installation

### Requirements

- ConcreteCMS v9.0 or higher
- Guzzle (comes with ConcreteCMS core)

### 1. Clone or copy the package

Place the package in your `packages` directory:

/packages/indexnow


Then install it via the ConcreteCMS Dashboard or CLI:

```./concrete/bin/concrete c5:package-install indexnow```

## ⚙️ Configuration
After installation, visit:

Dashboard → System & Settings → SEO & Statistics → IndexNow
There you can set:

API Key (required)

Endpoint URL (optional, defaults to https://api.indexnow.org/indexnow)

The API key must also be placed in a .txt file on your server root per IndexNow docs.

### ⚡ Automatic Submission
Whenever a page is published, the package:

- Checks if the page is public (non-system and not a draft)

- Resolves the canonical URL

- Submits it to the configured IndexNow endpoint

### 📌 Manual Task
To submit all public pages manually, run the task from:


Dashboard → Automated Jobs → IndexNow Submit
Or trigger it via CLI:

```./concrete/bin/concrete tasks:indexnow:submit```
This will:

- Scan all public pages

- Exclude drafts and system pages

- Submit them in bulk to the IndexNow endpoint
