-- Klusmaat: afbeeldingen (logo's, portfolio, blog) in Supabase Storage in plaats van als tekst in de database.
-- Bucket is openbaar leesbaar via de URL; schrijven alleen in de eigen map (klusser/<uid>/...) of, voor beheerders, in blog/.
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('afbeeldingen', 'afbeeldingen', true, 2097152, array['image/jpeg','image/png','image/webp'])
on conflict (id) do nothing;

create policy afb_insert on storage.objects for insert to authenticated
with check (bucket_id = 'afbeeldingen' and (
  ((storage.foldername(name))[1] = 'klusser' and (storage.foldername(name))[2] = auth.uid()::text)
  or ((storage.foldername(name))[1] = 'blog' and public.is_admin())));

create policy afb_delete on storage.objects for delete to authenticated
using (bucket_id = 'afbeeldingen' and (
  ((storage.foldername(name))[1] = 'klusser' and (storage.foldername(name))[2] = auth.uid()::text)
  or public.is_admin()));
