import { reportExcelFilename, reportPackageExcelXml } from "@/lib/report-excel";
import { getWorkspaceReportPackage } from "@/lib/workspace-data";

type ReportPackageExcelRouteProps = {
  params: Promise<{
    workspaceId: string;
    packageId: string;
  }>;
};

function contentDisposition(filename: string) {
  return `attachment; filename*=UTF-8''${encodeURIComponent(filename)}`;
}

export async function GET(_request: Request, { params }: ReportPackageExcelRouteProps) {
  const { workspaceId, packageId } = await params;
  const document = await getWorkspaceReportPackage(workspaceId, packageId);

  if (!document) {
    return new Response("Report package not found", { status: 404 });
  }

  return new Response(reportPackageExcelXml(document), {
    headers: {
      "Content-Disposition": contentDisposition(reportExcelFilename(document.reportPackage.title)),
      "Content-Type": "application/vnd.ms-excel; charset=utf-8"
    }
  });
}
