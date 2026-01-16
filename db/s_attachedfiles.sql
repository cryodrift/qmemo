WITH files AS (
    SELECT f.id,
           f.uid,
           f.name,
           f.path,
           f.fext,
           f.size,
           f.filedate,
           f.width,
           f.height,
           f.aratio,
           f.orientation,
           f.created,
           f.deleted,
           f.changed,
           IFNULL(m.name, '') || ' ' || IFNULL(m.title, '') AS memo,
           IFNULL(mf.memo_id, '') AS memo_id,
           m.deleted AS memo_deleted
    FROM files.files f
             LEFT JOIN memos_files mf ON f.id = mf.file_id
             LEFT JOIN memos m ON m.id = mf.memo_id
    WHERE (m.deleted != 'y' OR m.deleted IS NULL)
      AND mf.memo_id IS NOT NULL
)
SELECT id,
       uid,
       name,
       path,
       fext,
       size,
       filedate,
       width,
       height,
       aratio,
       orientation,
       created,
       deleted,
       changed,
       GROUP_CONCAT(memo, CHAR(10)) AS 'memotext',
       GROUP_CONCAT(memo_id, ',') AS 'memoids'
FROM files
GROUP BY id
LIMIT :limit OFFSET :offset;
