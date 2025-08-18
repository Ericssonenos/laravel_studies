
-- SELECT setval(
--   pg_get_serial_sequence('auth.usuarios','id_usuario'),
--   COALESCE((SELECT MAX(id_usuario) FROM auth.usuarios), 0) +1,
--   false
-- );
--DELETE FROM auth.usuarios;

--SELECT * from auth.usuarios ORDER BY dat_criado_em DESC;


INSERT INTO auth.usuarios_grupos (usuario_id, grupo_id)
                VALUES (1, 2)
                ON CONFLICT (usuario_id, grupo_id) DO NOTHING RETURNING *
