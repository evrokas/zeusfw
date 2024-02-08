Zeus Security System (ZeSec)

Security is implemented via permissions classes and database objects

Security levels
- Unauthenticated
- Authenticated
- Administrator

Each user in the database is assigned a permission GUID
(get random UUID from reading /proc/sys/kernel/random/uuid)

In the [routes] section of the info.yaml file a permissions field
is set, that will sets the permissions allowed to show the route

EvaluatePermissions is a function that returns the evaluated representation
of the permissions field, ie.

permissions: authenticated && user1

ZeSec scans the permissions database for current users permissions,
and replaces the tokens with 1 if they exist or 0 if the don't exist,
For example in the above example, if user is already authenticated and
has user1 permission assigned, the expression will be changed to

    1 && 1

if he has no 'user1' permision, then the expression is assigned:

    1 && 0

so the PHP express evaluator later can evaluate the result of the expression
and return the result.
