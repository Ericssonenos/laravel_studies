DO $$
DECLARE
    tbls TEXT;
BEGIN
    SELECT string_agg(format('auth.%I', tablename), ', ')
        INTO tbls
        FROM pg_tables
        WHERE schemaname = 'auth';

    IF tbls IS NOT NULL THEN
        EXECUTE format('TRUNCATE TABLE %s RESTART IDENTITY CASCADE;', tbls);
    END IF;
END
$$;
