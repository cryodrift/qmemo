SELECT m1.id,
       m1.type,
       m1.aktiv,
       m1.kat,
       m1.name,
       m1.title,
       m1.status,
       m1.created,
       m1.changed,
       m1.deleted,
       m1.contenttyp,
       (select group_concat(mm.memo2_id, ',') as ids from main.memos_memos mm where m1.id=mm.memo1_id group by mm.memo1_id) as 'memoids'
        ,   createShortContent(m1.content) as shortcontent
FROM memos m1,
     memos_memos as mm
where m1.id = mm.memo2_id
  and mm.memo1_id = :memo_id
LIMIT :limit OFFSET :offset;
