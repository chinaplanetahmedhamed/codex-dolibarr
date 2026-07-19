import { MobileApiClient, listQuery, mobileApiClient } from '@/src/api/client';
import { NetworkUnavailableError } from '@/src/api/errors';
import {
  ApiEnvelope,
  Balance,
  CommercialDocumentDetail,
  CommercialDocumentSummary,
  Dashboard,
  Document,
  ListEnvelope,
  ListQuery,
  Me,
  OrderSummary,
  ProjectDetail,
  ProjectSummary,
  ReportDetail,
  ReportSummary,
  ServiceInvoice,
  ShipmentDetail,
  ShipmentSummary,
  Transaction,
} from '@/src/api/types';
import { customerCache } from '@/src/storage/customerCache';

export class MobileRepository {
  constructor(private readonly client: MobileApiClient = mobileApiClient) {}

  me() { return this.cached('me', () => this.getOne<Me>('/me')); }
  dashboard() { return this.cached('dashboard', () => this.getOne<Dashboard>('/dashboard')); }
  projects(query?: ListQuery) { return this.cached(this.pageKey('projects', query), () => this.getList<ProjectSummary>('/projects', query)); }
  project(id: string) { return this.cached(`project_${id}`, () => this.getOne<ProjectDetail>(`/projects/${encodeURIComponent(id)}`)); }
  shipments(query?: ListQuery) { return this.cached(this.pageKey('shipments', query), () => this.getList<ShipmentSummary>('/shipments', query)); }
  shipment(id: string) { return this.cached(`shipment_${id}`, () => this.getOne<ShipmentDetail>(`/shipments/${encodeURIComponent(id)}`)); }
  orders(query?: ListQuery) { return this.cached(this.pageKey('orders', query), () => this.getList<OrderSummary>('/orders', query)); }
  balances() { return this.cached('balances', () => this.getArray<Balance>('/account/balances')); }
  transactions(query?: ListQuery) { return this.cached(this.pageKey('transactions', query), () => this.getList<Transaction>('/account/transactions', query)); }
  invoices(query?: ListQuery) { return this.cached(this.pageKey('service_invoices', query), () => this.getList<ServiceInvoice>('/service-invoices', query)); }
  documents(query?: ListQuery) { return this.cached(this.pageKey('documents', query), () => this.getList<Document>('/documents', query)); }
  commercialDocuments(query?: ListQuery) { return this.cached(this.pageKey('commercial_documents', query), () => this.getList<CommercialDocumentSummary>('/commercial-documents', query)); }
  commercialDocument(id: string) { return this.cached(`commercial_document_${id}`, () => this.getOne<CommercialDocumentDetail>(`/commercial-documents/${encodeURIComponent(id)}`)); }
  reports(query?: ListQuery) { return this.cached(this.pageKey('reports', query), () => this.getList<ReportSummary>('/reports', query)); }
  report(id: string) { return this.cached(`report_${id}`, () => this.getOne<ReportDetail>(`/reports/${encodeURIComponent(id)}`)); }

  private async getOne<T>(path: string) {
    const envelope = await this.client.request<ApiEnvelope<T>>(path);
    return envelope.data;
  }

  private async getArray<T>(path: string) {
    const envelope = await this.client.request<ApiEnvelope<T[]>>(path);
    return envelope.data;
  }

  private getList<T>(path: string, query?: ListQuery) {
    return this.client.request<ListEnvelope<T>>(path, { query: listQuery(query) });
  }

  private pageKey(resource: string, query?: ListQuery) {
    return `${resource}_${query?.cursor ?? 'first'}`;
  }

  private async cached<T>(key: string, loader: () => Promise<T>) {
    try {
      const value = await loader();
      await customerCache.writeJson(key, value);
      return value;
    } catch (error) {
      if (error instanceof NetworkUnavailableError) {
        const cached = await customerCache.readJson<T>(key);
        if (cached !== null) return cached;
      }
      throw error;
    }
  }
}

export const mobileRepository = new MobileRepository();
