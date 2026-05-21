# FeedbackFlow — User Guide
### For Teams & Companies

---

## Table of Contents

1. [What is FeedbackFlow?](#what-is-feedbackflow)
2. [Logging In](#logging-in)
3. [Projects](#projects)
4. [Collecting Feedback](#collecting-feedback)
5. [Managing Feedback](#managing-feedback)
6. [Roadmap](#roadmap)
7. [Changelog](#changelog)
8. [Analytics](#analytics)
9. [Team Management](#team-management)
10. [The Feedback Widget](#the-feedback-widget)
11. [Public Pages](#public-pages)
12. [Integrations](#integrations)
13. [Settings & Profile](#settings--profile)
14. [Frequently Asked Questions](#frequently-asked-questions)

---

## What is FeedbackFlow?

FeedbackFlow is a product feedback management system that your company hosts on its own server. It lets you:

- Collect feedback, bug reports, and feature requests from your users
- Organise and prioritise that feedback
- Plan your product roadmap based on what users actually want
- Publish a changelog to keep users informed
- Embed a feedback widget on any website with one line of code
- Manage multiple products or client websites from one place

Everything is stored on your own server — no third-party cloud, no subscription fees after purchase.

---

## Logging In

1. Go to your FeedbackFlow URL (e.g. `https://feedback.yourcompany.com`)
2. Enter your email and password
3. Click **Sign In**

If you have forgotten your password, contact your system administrator to reset it.

---

## Projects

A **Project** represents one product or website. Each project has its own:
- Feedback inbox
- Categories and tags
- Roadmap
- Changelog
- Widget embed code
- Public board

### Creating a Project

1. Go to **Projects** in the left sidebar
2. Click **New Project** (top right)
3. Enter a name, description, and website URL
4. Click **Create Project**

Default categories (Bug Report, Feature Request, Improvement, Question, Other) are created automatically.

### Switching Between Projects

Use the **project selector** at the top of the sidebar to switch. All pages — feedback, roadmap, analytics — will update to show data for the selected project.

### Project Settings

Go to **Projects → Settings (⚙ icon)** for any project to change:
- Name, description, website
- Whether the feedback board is public or private
- Whether anonymous feedback is allowed
- Widget appearance (colour, position, theme)

---

## Collecting Feedback

There are three ways users can submit feedback:

### 1. Public Feedback Board
Users visit your public board URL (e.g. `https://feedback.yourcompany.com/public/board.php?slug=my-app`) and submit directly. They can also vote on existing submissions.

### 2. Embeddable Widget
A floating button on your website opens a feedback panel. Users never leave your site. See [The Feedback Widget](#the-feedback-widget).

### 3. Admin Manual Entry
Your team can add feedback on behalf of users directly from the admin panel. Go to **Feedback → New Feedback**.

---

## Managing Feedback

The **Feedback** page is your main inbox. Every submission lands here.

### Viewing Feedback

Each item shows:
- Title and description
- Category, priority, status, and tags
- Number of votes
- Submission date and user

Click any item to open the full detail view.

### Feedback Statuses

| Status | Meaning |
|--------|---------|
| **New** | Just received, not yet reviewed |
| **Under Review** | Team is looking at it |
| **Planned** | Accepted and on the roadmap |
| **In Progress** | Being worked on right now |
| **Done** | Completed and shipped |
| **Declined** | Will not be actioned |

### Updating a Feedback Item

From the detail view you can:
- Change the **status**, **priority**, or **category**
- Add **internal notes** (only your team sees these)
- Add **public comments** (visible to the user)
- Attach **files**
- Add or remove **tags**
- **Merge** duplicates into one item
- Run **AI Analysis** to get a sentiment score and summary (requires OpenAI key)

### Filtering & Searching

Use the filter bar at the top of the Feedback page to filter by:
- Status, Priority, Category, Tag
- Date range
- Search by keyword

---

## Roadmap

The **Roadmap** is a kanban-style board with three columns:

| Column | Meaning |
|--------|---------|
| **Planned** | Accepted items scheduled for future work |
| **In Progress** | Currently being built |
| **Done** | Shipped |

Items appear on the roadmap when their status is set to Planned, In Progress, or Done. You can drag items between columns directly on the board.

You can also set a **target date** on each item from the feedback detail view.

### Public Roadmap
Your roadmap can be shared publicly at:
`https://yourfeedbackflow.com/public/roadmap.php?slug=your-project`

---

## Changelog

The **Changelog** is where you announce what you have shipped.

### Creating a Changelog Entry

1. Go to **Changelog** in the sidebar
2. Click **New Entry**
3. Fill in the title, version number, and content
4. Choose a **type**: Feature, Improvement, Bug Fix, Security, or Breaking Change
5. Save as **Draft** or **Publish** immediately

Published entries appear on the public changelog page automatically.

### Public Changelog
`https://yourfeedbackflow.com/public/changelog.php?slug=your-project`

---

## Analytics

The **Analytics** page gives you an overview of feedback trends:

- **Total feedback** received
- **New feedback** this period
- **Votes** cast
- **Average sentiment** score (if AI is enabled)
- Feedback over time (line chart)
- Breakdown by status, category, and priority
- Top-voted feedback items

Use the date range selector to compare different periods.

---

## Team Management

Go to **Team** in the sidebar to manage who has access.

### Roles

| Role | What they can do |
|------|-----------------|
| **Owner** | Full access, can delete the project |
| **Admin** | Full access except deleting the project |
| **Manager** | Can manage feedback, roadmap, changelog |
| **Member** | Can view and comment on feedback |
| **Viewer** | Read-only access |

### Inviting a Team Member

1. Go to **Team**
2. Enter the person's email address
3. Select their role
4. Click **Invite**

They will receive an email with a link to set their password and join.

---

## The Feedback Widget

The widget is a small button that floats on your website. When clicked, it opens a panel where users can submit feedback without leaving your site.

### Installing the Widget

1. Go to **Widget** in the sidebar
2. Copy the script tag shown
3. Paste it into your website's HTML just before `</body>`

```html
<script src="https://yourfeedbackflow.com/widget/widget.js"
        data-key="YOUR_PROJECT_KEY"
        defer></script>
```

That's it. The widget button appears automatically.

### One FeedbackFlow, Many Websites

Each project has its own unique widget key. To collect feedback from 10 different websites:
- Create a project for each website
- Copy that project's widget script tag
- Paste it into the corresponding website

All feedback flows into FeedbackFlow, separated by project.

### Widget Customisation

Change the widget appearance from **Projects → Settings (⚙)**:
- **Brand colour** — matches your website's colour
- **Position** — bottom-right, bottom-left, top-right, top-left
- **Theme** — Light, Dark, or Auto (follows the user's system)
- **Title** — the heading shown inside the widget panel
- **Placeholder text** — the hint shown in the feedback text box

---

## Public Pages

Each project has three public-facing pages that anyone can visit — no login required:

| Page | URL | Purpose |
|------|-----|---------|
| Feedback Board | `/public/board.php?slug=PROJECT` | Submit and vote on feedback |
| Roadmap | `/public/roadmap.php?slug=PROJECT` | See what's planned and in progress |
| Changelog | `/public/changelog.php?slug=PROJECT` | See what's been shipped |

You can link to these pages from your website, in your app, or in email newsletters.

To make these pages private, go to **Projects → Settings** and disable **Public board**.

---

## Integrations

Go to **Integrations** in the sidebar to connect external tools.

### Slack
Get a notification in a Slack channel every time new feedback is submitted. Requires a Slack Incoming Webhook URL.

### Email Notifications
Receive an email for every new submission. Configure your SMTP settings under **Settings → System**.

### Jira
Automatically create a Jira ticket from any feedback item. Requires your Jira domain, email, and API token.

### Zapier / Webhooks
Send feedback data to any tool (Notion, Trello, HubSpot, etc.) via Zapier or a custom webhook URL.

---

## Settings & Profile

### Profile
Go to **Settings → Profile** to update your name, email, and avatar.

### Password
Go to **Settings → Password** to change your password.

### GDPR / Data Export
Go to **Settings → Privacy** to export all your personal data as a JSON file.

### System Settings (Admins only)
Go to **Settings → System** to configure:
- Application name and URL
- SMTP email settings
- OpenAI API key (for AI features)
- Session timeout

---

## Frequently Asked Questions

**Can users vote without an account?**
Yes — if you enable **Anonymous feedback** in Project Settings, users can vote and submit without signing in.

**How many projects can I create?**
Unlimited.

**How many team members can I invite?**
Unlimited.

**Where is the feedback data stored?**
On your own server, in your MySQL database. No data leaves your server.

**What happens if I regenerate the widget key?**
The old key stops working immediately. You must update the script tag on all websites that used the old key.

**Can I customise the categories?**
Yes — go to **Projects → Settings (⚙) → Categories** to add, remove, or recolour categories for any project.

**Is there a mobile app?**
No — FeedbackFlow is a web-based admin panel. It works on mobile browsers but is optimised for desktop.

**How do I enable AI features?**
Add your OpenAI API key to `config.php` (or under Settings → System if your admin exposes it). The AI Analysis button will then appear on each feedback detail page.

---

*FeedbackFlow — Self-hosted product feedback management*
