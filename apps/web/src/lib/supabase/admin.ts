import { createClient as createSupabaseAdminClient } from "@supabase/supabase-js";
import { getPublicEnv } from "@/lib/env";

export function hasSupabaseAdminEnv() {
  const env = getPublicEnv();

  return Boolean(env.supabaseUrl && process.env.SUPABASE_SERVICE_ROLE_KEY);
}

export function createAdminClient() {
  const env = getPublicEnv();
  const serviceRoleKey = process.env.SUPABASE_SERVICE_ROLE_KEY;

  if (!env.supabaseUrl || !serviceRoleKey) {
    throw new Error("Supabase admin environment is not configured.");
  }

  return createSupabaseAdminClient(env.supabaseUrl, serviceRoleKey, {
    auth: {
      autoRefreshToken: false,
      persistSession: false
    }
  });
}
