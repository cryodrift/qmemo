select *
from memos
WHERE (changed IS NOT NULL AND changed > datetime(:tst, 'unixepoch'))
   OR (changed IS NULL AND created > datetime(:tst, 'unixepoch'))
order by changed desc, created desc
