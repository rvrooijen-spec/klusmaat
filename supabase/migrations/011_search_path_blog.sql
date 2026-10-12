-- Klusmaat: vaste search_path voor de blog-trigger (beveiligingsadvies).
alter function public.blog_tijdstempel() set search_path = public, pg_temp;
