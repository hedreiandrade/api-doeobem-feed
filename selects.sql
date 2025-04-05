-- Aqui está uma query que retorna todos os posts das pessoas que você segue, 
-- mais os seus próprios posts, ordenados por data de criação (do mais recente para o mais antigo)
SELECT 
    p.id AS post_id,
    p.description,
    p.media_link,
    p.created_at,
    u.id AS user_id,
    u.name AS user_name,
    u.nickname,
    u.photo AS user_photo,
    CASE 
        WHEN p.id IN (SELECT post_id FROM posts_users WHERE user_id = 85) THEN 1
        ELSE 0
    END AS is_my_post
FROM 
    posts p
JOIN 
    posts_users pu ON p.id = pu.post_id
JOIN 
    users u ON pu.user_id = u.id
WHERE 
    pu.user_id = 85
    OR pu.user_id IN (
        SELECT user_id 
        FROM followers 
        WHERE follower_id = 85
        AND deleted_at IS NULL
    )
ORDER BY 
    p.created_at DESC;