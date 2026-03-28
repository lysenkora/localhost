class API {
    static async request(url, method = 'GET', data = null) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(url, options);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.message || 'Request failed');
            }
            
            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
    
    static getOperations(page = 1, filters = {}) {
        const params = new URLSearchParams({ page, ...filters });
        return this.request(`/api/operations?${params}`);
    }
    
    static addTrade(data) {
        return this.request('/api/add_trade', 'POST', data);
    }
    
    static addDeposit(data) {
        return this.request('/api/add_deposit', 'POST', data);
    }
    
    static addTransfer(data) {
        return this.request('/api/add_transfer', 'POST', data);
    }
    
    static addNote(data) {
        return this.request('/api/add_note', 'POST', data);
    }
    
    static updateNote(data) {
        return this.request('/api/update_note', 'POST', data);
    }
    
    static deleteNote(id) {
        const formData = new FormData();
        formData.append('note_id', id);
        return fetch('/api/delete_note', { method: 'POST', body: formData }).then(r => r.json());
    }
    
    static addLimitOrder(data) {
        return this.request('/api/add_limit_order', 'POST', data);
    }
    
    static executeLimitOrder(id) {
        const formData = new FormData();
        formData.append('order_id', id);
        return fetch('/api/execute_limit_order', { method: 'POST', body: formData }).then(r => r.json());
    }
    
    static cancelLimitOrder(id) {
        const formData = new FormData();
        formData.append('order_id', id);
        return fetch('/api/cancel_limit_order', { method: 'POST', body: formData }).then(r => r.json());
    }
    
    static addExpense(data) {
        return this.request('/api/add_expense', 'POST', data);
    }
    
    static getExpenses(params = {}) {
        const formData = new FormData();
        Object.entries(params).forEach(([key, value]) => {
            if (value) formData.append(key, value);
        });
        return fetch('/api/get_expenses', { method: 'POST', body: formData }).then(r => r.json());
    }
    
    static deleteExpense(id) {
        const formData = new FormData();
        formData.append('expense_id', id);
        return fetch('/api/delete_expense', { method: 'POST', body: formData }).then(r => r.json());
    }
    
    static getExpenseCategories() {
        return this.request('/api/get_expense_categories');
    }
    
    static saveTheme(theme) {
        const formData = new FormData();
        formData.append('theme', theme);
        return fetch('/api/save_theme', { method: 'POST', body: formData }).then(r => r.json());
    }
}