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
       case when mf.file_id is not null then true else false end as file_has_memo
from files.files f
         left join memos_files mf on f.id = mf.file_id and mf.memo_id = :id
where (:withdeleted != 'y' AND (f.deleted != 'y' OR f.deleted IS NULL))
   OR (:withdeleted = 'y' AND f.deleted = 'y')
limit :limit offset :offset;
