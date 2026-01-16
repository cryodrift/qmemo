WITH fts_results AS (SELECT id
                     from ftsfiles.files_fts
                     WHERE files_fts MATCH :query)
   , found_files as (select m.id,
                            m.uid,
                            m.name,
                            m.path,
                            m.fext,
                            m.size,
                            m.filedate,
                            m.width,
                            m.height,
                            m.aratio,
                            m.orientation,
                            m.changed,
                            m.deleted,
                            m.created
                     FROM files.files m
                              JOIN fts_results fr ON m.id = fr.id)
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
       exists (SELECT 1 FROM memos_files mf WHERE mf.file_id = f.id) AS file_has_memo
from found_files f
WHERE (:withdeleted != 'y' AND (f.deleted != 'y' OR f.deleted IS NULL))
   OR (:withdeleted = 'y' AND f.deleted = 'y')
limit :limit offset :offset;
