<?php
/**
 * FeedbackFlow – English language strings (Module 19 – Multi-Language)
 * Usage: __('key') or __('key', ['name' => 'John'])
 */
return [
    // General
    'app_name'            => 'FeedbackFlow',
    'welcome'             => 'Welcome back',
    'save'                => 'Save',
    'cancel'              => 'Cancel',
    'delete'              => 'Delete',
    'edit'                => 'Edit',
    'create'              => 'Create',
    'submit'              => 'Submit',
    'close'               => 'Close',
    'loading'             => 'Loading…',
    'no_data'             => 'No data found.',
    'search'              => 'Search',
    'filter'              => 'Filter',
    'export'              => 'Export',
    'import'              => 'Import',
    'download'            => 'Download',
    'upload'              => 'Upload',
    'back'                => 'Back',
    'next'                => 'Next',
    'previous'            => 'Previous',
    'confirm'             => 'Confirm',
    'yes'                 => 'Yes',
    'no'                  => 'No',
    'active'              => 'Active',
    'inactive'            => 'Inactive',
    'enabled'             => 'Enabled',
    'disabled'            => 'Disabled',
    'required'            => 'Required',
    'optional'            => 'Optional',

    // Auth
    'sign_in'             => 'Sign In',
    'sign_out'            => 'Sign Out',
    'sign_up'             => 'Create Account',
    'forgot_password'     => 'Forgot Password?',
    'reset_password'      => 'Reset Password',
    'email'               => 'Email Address',
    'password'            => 'Password',
    'confirm_password'    => 'Confirm Password',
    'remember_me'         => 'Remember me',
    'invalid_credentials' => 'Invalid email or password.',
    'account_disabled'    => 'Your account has been suspended.',

    // Dashboard
    'dashboard'           => 'Dashboard',
    'total_feedback'      => 'Total Feedback',
    'new_feedback'        => 'New Feedback',
    'planned'             => 'Planned',
    'done'                => 'Done',
    'votes'               => 'Votes',
    'recent_activity'     => 'Recent Activity',
    'no_project_selected' => 'Please select a project.',
    'create_project'      => 'Create Your First Project',

    // Feedback
    'feedback'            => 'Feedback',
    'submit_feedback'     => 'Submit Feedback',
    'feedback_title'      => 'Feedback Title',
    'description'         => 'Description',
    'category'            => 'Category',
    'status'              => 'Status',
    'priority'            => 'Priority',
    'rating'              => 'Rating',
    'sentiment'           => 'Sentiment',
    'vote'                => 'Vote',
    'voted'               => 'Voted',
    'comment'             => 'Comment',
    'reply'               => 'Reply',
    'internal_note'       => 'Internal Note',
    'ai_reply'            => 'AI Reply',

    // Status values
    'status_new'          => 'New',
    'status_under_review' => 'Under Review',
    'status_planned'      => 'Planned',
    'status_in_progress'  => 'In Progress',
    'status_done'         => 'Done',
    'status_declined'     => 'Declined',
    'status_duplicate'    => 'Duplicate',

    // Priority values
    'priority_critical'   => 'Critical',
    'priority_high'       => 'High',
    'priority_medium'     => 'Medium',
    'priority_low'        => 'Low',

    // Projects
    'projects'            => 'Projects',
    'project_name'        => 'Project Name',
    'project_slug'        => 'Project Slug (URL)',
    'project_description' => 'Description',
    'project_website'     => 'Website URL',
    'widget_key'          => 'Widget Key',
    'no_projects'         => 'No projects yet. Create your first!',

    // Team
    'team'                => 'Team',
    'invite_member'       => 'Invite Member',
    'member_role'         => 'Role',
    'role_owner'          => 'Owner',
    'role_admin'          => 'Admin',
    'role_manager'        => 'Manager',
    'role_member'         => 'Member',
    'role_viewer'         => 'Viewer',

    // Billing
    'billing'             => 'Billing & Payments',
    'current_plan'        => 'Current Plan',
    'upgrade_plan'        => 'Upgrade Plan',
    'downgrade_plan'      => 'Downgrade Plan',
    'invoices'            => 'Invoice History',
    'free_plan'           => 'Free',
    'starter_plan'        => 'Starter',
    'growth_plan'         => 'Growth',
    'pro_plan'            => 'Pro',
    'enterprise_plan'     => 'Enterprise',
    'per_month'           => '/month',
    'per_year'            => '/year',

    // Suppression
    'suppression_list'    => 'Suppression List',
    'add_suppression'     => 'Add Entry',
    'bulk_import'         => 'Bulk Import',
    'suppression_email'   => 'Email',
    'suppression_phone'   => 'Phone',
    'suppression_domain'  => 'Domain',
    'reason_unsubscribe'  => 'Unsubscribe',
    'reason_bounce'       => 'Bounce',
    'reason_complaint'    => 'Complaint',
    'reason_manual'       => 'Manual',
    'reason_gdpr'         => 'GDPR Request',

    // Automations
    'automations'         => 'Automations',
    'new_automation'      => 'New Automation',
    'trigger'             => 'Trigger',
    'action'              => 'Action',
    'automation_active'   => 'Active',
    'automation_paused'   => 'Paused',
    'runs'                => 'Runs',

    // Review Booster
    'review_booster'      => 'Review Booster',
    'platform'            => 'Platform',
    'review_url'          => 'Review URL',
    'min_rating'          => 'Minimum Rating',
    'requests_sent'       => 'Requests Sent',
    'conversion_rate'     => 'Conversion Rate',

    // Notifications
    'notifications'       => 'Notifications',
    'mark_read'           => 'Mark as Read',
    'mark_all_read'       => 'Mark All Read',
    'no_notifications'    => 'No notifications. You\'re all caught up!',
    'unread'              => 'Unread',

    // Audit Logs
    'audit_logs'          => 'Audit Logs',
    'action_performed'    => 'Action',
    'performed_by'        => 'Performed By',
    'ip_address'          => 'IP Address',
    'timestamp'           => 'Timestamp',

    // API Keys
    'api_keys'            => 'API Keys',
    'create_key'          => 'Create API Key',
    'key_name'            => 'Key Name',
    'permissions'         => 'Permissions',
    'last_used'           => 'Last Used',
    'revoke'              => 'Revoke',
    'key_copied'          => 'Key copied! Store it safely — it won\'t be shown again.',

    // Reports
    'reports'             => 'Reports & Metrics',
    'period'              => 'Period',
    'positive_sentiment'  => 'Positive Sentiment',
    'negative_sentiment'  => 'Negative Sentiment',
    'neutral_sentiment'   => 'Neutral',
    'resolution_rate'     => 'Resolution Rate',
    'avg_rating'          => 'Average Rating',
    'by_source'           => 'By Source',
    'by_category'         => 'By Category',
    'feedback_volume'     => 'Feedback Volume',

    // Tasks
    'tasks'               => 'Tasks',
    'new_task'            => 'New Task',
    'task_title'          => 'Task Title',
    'assigned_to'         => 'Assigned To',
    'due_date'            => 'Due Date',
    'mark_done'           => 'Mark Done',
    'no_tasks'            => 'No tasks found.',

    // Status Page
    'status_page'         => 'System Status',
    'all_operational'     => 'All Systems Operational',
    'incidents'           => 'Incidents',
    'no_incidents'        => 'No incidents reported.',
    'report_incident'     => 'Report Incident',
    'severity'            => 'Severity',
    'investigating'       => 'Investigating',
    'identified'          => 'Identified',
    'monitoring'          => 'Monitoring',
    'resolved'            => 'Resolved',

    // Export
    'export_data'         => 'Export Data',
    'import_contacts'     => 'Import Contacts',
    'download_csv'        => 'Download CSV',
    'gdpr_erase'          => 'Erase Customer Data',

    // Widget
    'widget'              => 'Feedback Widget',
    'widget_embed'        => 'Embed on your site',
    'widget_color'        => 'Widget Color',
    'widget_position'     => 'Widget Position',
    'widget_title'        => 'Widget Title',

    // Errors
    'error_generic'       => 'An unexpected error occurred. Please try again.',
    'error_unauthorized'  => 'You do not have permission to perform this action.',
    'error_not_found'     => 'The requested resource was not found.',
    'error_csrf'          => 'Security token expired. Please refresh and try again.',

    // Flash
    'saved'               => 'Changes saved successfully.',
    'deleted'             => 'Deleted successfully.',
    'created'             => 'Created successfully.',
    'updated'             => 'Updated successfully.',
];
