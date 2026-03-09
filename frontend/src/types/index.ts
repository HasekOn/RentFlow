export interface User {
    id: number
    name: string
    email: string
    role: 'landlord' | 'tenant' | 'manager'
    trust_score: number
    phone: string | null
    created_at: string
}

export interface Property {
    id: number
    address: string
    city: string | null
    zip_code: string | null
    size: number | null
    disposition: string | null
    floor: number | null
    status: 'available' | 'occupied' | 'renovation'
    purchase_price: number | null
    description: string | null
    created_at: string
    landlord?: User
    leases?: Lease[]
    meters?: Meter[]
    images?: PropertyImage[]
}

export interface Lease {
    id: number
    start_date: string
    end_date: string | null
    rent_amount: number
    deposit_amount: number | null
    utility_advances: number | null
    variable_symbol: string | null
    contract_path: string | null
    status: 'active' | 'ended' | 'terminated'
    created_at: string
    property?: Property
    tenant?: User
    payments?: Payment[]
    ratings?: Rating[]
}

export interface Payment {
    id: number
    type: 'rent' | 'utilities' | 'deposit' | 'other'
    amount: number
    due_date: string
    paid_date: string | null
    variable_symbol: string | null
    status: 'paid' | 'unpaid' | 'overdue'
    note: string | null
    days_overdue: number | null
    created_at: string
    lease?: Lease
}

export interface Expense {
    id: number
    type: 'repair' | 'insurance' | 'tax' | 'maintenance' | 'other'
    amount: number
    expense_date: string
    description: string | null
    invoice_path: string | null
    created_at: string
    property?: Property
}

export interface Ticket {
    id: number
    title: string
    description: string
    category: string | null
    status: 'new' | 'in_progress' | 'resolved' | 'rejected'
    priority: 'low' | 'medium' | 'high' | 'urgent'
    resolved_at: string | null
    resolution_time: string | null
    created_at: string
    property?: Property
    tenant?: User
    assigned_user?: User
    comments?: TicketComment[]
}

export interface TicketComment {
    id: number
    message: string
    attachment_path: string | null
    attachment_url: string | null
    created_at: string
    user?: User
}

export interface Meter {
    id: number
    meter_type: 'water' | 'electricity' | 'gas' | 'heat'
    serial_number: string | null
    location: string | null
    latest_reading: { value: number; date: string } | null
    created_at: string
    property?: Property
    readings?: MeterReading[]
}

export interface MeterReading {
    id: number
    reading_value: number
    reading_date: string
    photo_proof: string | null
    created_at: string
    submitted_by?: User
}

export interface PropertyImage {
    id: number
    image_path: string
    image_url: string
    type: 'marketing' | 'defect' | 'document'
    description: string | null
    sort_order: number
    created_at: string
    uploaded_by?: User
}

export interface InventoryItem {
    id: number
    name: string
    category: string | null
    condition: 'new' | 'good' | 'fair' | 'poor' | 'broken'
    purchase_date: string | null
    purchase_price: number | null
    note: string | null
    created_at: string
}

export interface Rating {
    id: number
    category: 'apartment_condition' | 'communication' | 'rules' | 'overall'
    score: number
    comment: string | null
    created_at: string
    rated_by?: User
}

export interface Notice {
    id: number
    title: string
    content: string
    is_active: boolean
    created_at: string
    created_by?: User
}

export interface Notification {
    id: string
    type: string
    data: Record<string, unknown>
    read: boolean
    created_at: string
}

export interface PaginatedResponse<T> {
    data: T[]
    links: {
        first: string
        last: string
        prev: string | null
        next: string | null
    }
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
    }
}

export interface ApiError {
    message: string
    error: string
    errors?: Record<string, string[]>
}

export interface DashboardStats {
    properties: {
        total: number
        occupied: number
        available: number
        renovation: number
    }
    finance: {
        monthly_income: number
        monthly_expenses: number
        cashflow: number
        overdue_payments: number
    }
    leases: {
        active: number
        expiring_soon: number
    }
    tickets: {
        open: number
    }
}

export interface FinanceChartData {
    month: string
    label: string
    income: number
    expenses: number
    cashflow: number
}

export interface OccupancyChartData {
    label: string
    value: number
}

export interface TrustScoreData {
    tenant_id: number
    tenant_name: string
    trust_score: number
    breakdown: {
        total_payments: number
        on_time_payments: number
        payment_ratio: string
        average_rating: string
    }
}
