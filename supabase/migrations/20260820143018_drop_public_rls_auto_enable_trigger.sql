-- Remove early public SECURITY DEFINER RLS helper from the empty Supabase project.
-- The project advisor reported public execute access through the event trigger.

drop event trigger if exists ensure_rls;
drop function if exists public.rls_auto_enable();
