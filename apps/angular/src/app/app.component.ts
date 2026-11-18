import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';

interface ApiStatus {
  status: string;
  providers?: Record<string, boolean>;
  server_time?: string;
}

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})
export class AppComponent implements OnInit {
  readonly title = 'AI Billing & Cloud Hub — Angular';
  readonly apiStatus = signal<ApiStatus | null>(null);
  readonly error = signal<string | null>(null);

  async ngOnInit(): Promise<void> {
    try {
      const res = await fetch('/api/php?action=status');
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      this.apiStatus.set(await res.json());
    } catch (err) {
      this.error.set(err instanceof Error ? err.message : String(err));
      this.apiStatus.set({ status: 'offline' });
    }
  }
}
