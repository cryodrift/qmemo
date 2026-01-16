select f.id,
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
       true                      as 'file_has_memo',
       (select group_concat(IFNULL(m.name, '') || ' ' || IFNULL(m.title, ''), char(10))
        from memos_files mf,
             memos m
        where mf.file_id = f.id
          and m.id != :id
          and m.id = mf.memo_id) as 'memotext',
       (select group_concat(IFNULL(m.id, ''), ',')
        from memos_files mf,
             memos m
        where mf.file_id = f.id
          and m.id = mf.memo_id
          and m.id != :id
        group by mf.file_id)     as 'memoids'
from files.files f,
     memos_files,
     memos
where memos.id = :id
    and memos_files.memo_id = memos.id
    and f.id = memos_files.file_id
    AND (:withdeleted != 'y' AND (f.deleted != 'y' OR f.deleted IS NULL))
   OR (:withdeleted = 'y' AND f.deleted = 'y')

limit :limit offset :offset;
