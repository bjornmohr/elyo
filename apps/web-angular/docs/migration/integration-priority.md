# Integration Priority Ranking

This document ranks the external integrations and lower-priority features for the ELYO migration.

## 1. High Priority (MVP Core)
- **Partner Authentication & Basic Dashboard**: Necessary for partners to view their status and manage documents.
- **Admin Partner Review**: Essential for the ELYO admin to approve/reject partners.
- **Storage Abstraction**: Replacing Vercel Blob with a Laravel-native storage system (Local/S3) is critical for document handling.
- **Push Notifications**: Essential for user engagement and streak reminders.

## 2. Medium Priority (Functional Enhancements)
- **Terra Integration**: Wearable data is a key feature but can be enabled after the core app is stable.
- **Google Health Integration**: Similar to Terra, provides alternative wearable data source.
- **Documents**: Partner and user documents (Health documents already migrated in models, but need actual file handling).

## 3. Low Priority (Automation & Advanced Features)
- **n8n Webhook Integration**: For advanced workflows that aren't core to the app logic (e.g., syncing data to external CRMs or complex notification chains).

## Integration Strategy
- **Laravel Services**: All external APIs (Google, Terra, Push) must be wrapped in Laravel Service classes.
- **Feature Flags**: Wearable integrations should be behind feature flags to allow testing without blocking the main release.
- **Mocking**: All external services must have mock implementations for testing.
