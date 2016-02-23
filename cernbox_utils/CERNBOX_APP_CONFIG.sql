-- Disable SSL Root certificates management for users
INSERT INTO oc_appconfig VALUES ('files_external', 'allow_user_mounting', 'no');