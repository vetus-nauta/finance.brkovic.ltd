import { createClient as createSupabaseAdminClient } from "@supabase/supabase-js";
import { getPublicEnv } from "@/lib/env";

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
